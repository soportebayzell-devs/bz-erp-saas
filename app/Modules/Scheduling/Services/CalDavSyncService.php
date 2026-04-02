<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\CalendarAccount;
use App\Modules\Scheduling\Models\ScheduledEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalDavSyncService
{
    // ── Push event TO Nextcloud ───────────────────────────────────

    public function pushEvent(ScheduledEvent $event): bool
    {
        $account = $event->calendarAccount;
        if (! $account || ! $account->is_active) return false;

        $uid    = $event->caldav_uid ?? (string) Str::uuid();
        $ical   = $this->buildIcal($event, $uid);
        $url    = rtrim($account->caldav_url, '/') . '/' .
                  ltrim($account->calendar_path, '/') . '/' .
                  $uid . '.ics';

        $response = Http::withBasicAuth($account->username, $account->password)
            ->withHeaders(['Content-Type' => 'text/calendar; charset=utf-8'])
            ->put($url, $ical);

        if ($response->successful()) {
            $event->update([
                'caldav_uid'       => $uid,
                'caldav_etag'      => $response->header('ETag'),
                'caldav_data'      => $ical,
                'caldav_synced_at' => now(),
            ]);
            return true;
        }

        Log::error('CalDAV push failed', [
            'event_id' => $event->id,
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);

        return false;
    }

    // ── Delete event FROM Nextcloud ───────────────────────────────

    public function deleteEvent(ScheduledEvent $event): bool
    {
        if (! $event->caldav_uid) return true;

        $account = $event->calendarAccount;
        if (! $account) return true;

        $url = rtrim($account->caldav_url, '/') . '/' .
               ltrim($account->calendar_path, '/') . '/' .
               $event->caldav_uid . '.ics';

        $response = Http::withBasicAuth($account->username, $account->password)
            ->delete($url);

        return $response->successful() || $response->status() === 404;
    }

    // ── Pull events FROM Nextcloud ────────────────────────────────

    public function pullEvents(CalendarAccount $account, string $from, string $to): array
    {
        $calendarUrl = rtrim($account->caldav_url, '/') . '/' .
                       ltrim($account->calendar_path, '/') . '/';

        // CalDAV calendar-query REPORT request
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:getetag/>
        <c:calendar-data/>
    </d:prop>
    <c:filter>
        <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT">
                <c:time-range start="{$from}" end="{$to}"/>
            </c:comp-filter>
        </c:comp-filter>
    </c:filter>
</c:calendar-query>
XML;

        $response = Http::withBasicAuth($account->username, $account->password)
            ->withHeaders([
                'Content-Type' => 'application/xml',
                'Depth'        => '1',
            ])
            ->send('REPORT', $calendarUrl, ['body' => $xml]);

        if (! $response->successful()) {
            Log::error('CalDAV pull failed', ['account' => $account->id, 'status' => $response->status()]);
            return [];
        }

        return $this->parseCalDavResponse($response->body());
    }

    // ── Sync pulled events into local DB ──────────────────────────

    public function syncFromCalDav(CalendarAccount $account, string $from, string $to): int
    {
        $remoteEvents = $this->pullEvents($account, $from, $to);
        $synced = 0;

        foreach ($remoteEvents as $remote) {
            $parsed = $this->parseIcal($remote['ical']);
            if (! $parsed) continue;

            ScheduledEvent::updateOrCreate(
                ['caldav_uid' => $remote['uid']],
                [
                    'tenant_id'          => $account->tenant_id,
                    'calendar_account_id'=> $account->id,
                    'title'              => $parsed['summary'] ?? 'Untitled',
                    'description'        => $parsed['description'] ?? null,
                    'location'           => $parsed['location'] ?? null,
                    'starts_at'          => $parsed['dtstart'],
                    'ends_at'            => $parsed['dtend'],
                    'caldav_etag'        => $remote['etag'],
                    'caldav_data'        => $remote['ical'],
                    'caldav_synced_at'   => now(),
                    'type'               => 'class',
                    'status'             => 'scheduled',
                ]
            );
            $synced++;
        }

        $account->update(['last_synced_at' => now(), 'sync_error' => null]);

        return $synced;
    }

    // ── Build iCal string from ScheduledEvent ─────────────────────

    private function buildIcal(ScheduledEvent $event, string $uid): string
    {
        $dtstart  = $event->starts_at->utc()->format('Ymd\THis\Z');
        $dtend    = $event->ends_at->utc()->format('Ymd\THis\Z');
        $dtstamp  = now()->utc()->format('Ymd\THis\Z');
        $summary  = $this->escapeIcal($event->title);
        $desc     = $this->escapeIcal($event->description ?? '');
        $location = $this->escapeIcal($event->location ?? '');

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Bayzell ERP//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:{$uid}\r\n";
        $ical .= "DTSTAMP:{$dtstamp}\r\n";
        $ical .= "DTSTART:{$dtstart}\r\n";
        $ical .= "DTEND:{$dtend}\r\n";
        $ical .= "SUMMARY:{$summary}\r\n";
        if ($desc)     $ical .= "DESCRIPTION:{$desc}\r\n";
        if ($location) $ical .= "LOCATION:{$location}\r\n";
        if ($event->recurrence_rule) $ical .= "RRULE:{$event->recurrence_rule}\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    // ── Parse raw iCal string ─────────────────────────────────────

    private function parseIcal(string $ical): ?array
    {
        $lines   = preg_split('/\r\n|\r|\n/', $ical);
        $data    = [];
        $inEvent = false;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') { $inEvent = true; continue; }
            if ($line === 'END:VEVENT')   { $inEvent = false; continue; }
            if (! $inEvent) continue;

            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            $key = strtoupper(explode(';', $key)[0]);

            $data[strtolower($key)] = match ($key) {
                'DTSTART', 'DTEND' => $this->parseIcalDate($value),
                default            => $value,
            };
        }

        return isset($data['dtstart'], $data['dtend']) ? $data : null;
    }

    private function parseIcalDate(string $value): \Carbon\Carbon
    {
        return \Carbon\Carbon::createFromFormat(
            str_ends_with($value, 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis',
            $value
        );
    }

    private function parseCalDavResponse(string $xml): array
    {
        $events = [];
        try {
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('d', 'DAV:');
            $xpath->registerNamespace('c', 'urn:ietf:params:xml:ns:caldav');

            foreach ($xpath->query('//d:response') as $response) {
                $ical = $xpath->query('.//c:calendar-data', $response)->item(0)?->textContent ?? '';
                $etag = $xpath->query('.//d:getetag', $response)->item(0)?->textContent ?? '';
                $href = $xpath->query('.//d:href', $response)->item(0)?->textContent ?? '';

                if ($ical) {
                    preg_match('/UID:(.+)/i', $ical, $uidMatch);
                    $events[] = [
                        'uid'  => trim($uidMatch[1] ?? basename($href, '.ics')),
                        'etag' => trim($etag, '"'),
                        'ical' => $ical,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('CalDAV XML parse error', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    private function escapeIcal(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';'], ['\\n', '\\n', '\\n', '\,', '\;'], $value);
    }
}
