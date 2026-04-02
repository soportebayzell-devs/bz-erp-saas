<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\CalendarAccount;
use App\Modules\Scheduling\Models\ScheduledEvent;
use App\Modules\Scheduling\Services\CalDavSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchedulingController extends Controller
{
    public function __construct(
        private readonly CalDavSyncService $calDav
    ) {}

    // ── Events ────────────────────────────────────────────────────

    // GET /api/v1/scheduling/events
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'course_id' => 'nullable|uuid',
            'coach_id'  => 'nullable|uuid',
            'type'      => 'nullable|string',
        ]);

        $events = ScheduledEvent::with(['course:id,name', 'coach:id,name'])
            ->when($request->from,      fn ($q) => $q->where('starts_at', '>=', $request->from))
            ->when($request->to,        fn ($q) => $q->where('starts_at', '<=', $request->to))
            ->when($request->course_id, fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->coach_id,  fn ($q) => $q->where('coach_id', $request->coach_id))
            ->when($request->type,      fn ($q) => $q->where('type', $request->type))
            ->orderBy('starts_at')
            ->paginate($request->per_page ?? 50);

        return response()->json($events);
    }

    // POST /api/v1/scheduling/events
    public function createEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => 'required|string|max:200',
            'type'                => 'nullable|in:class,meeting,event,holiday',
            'course_id'           => 'nullable|uuid|exists:courses,id',
            'coach_id'            => 'nullable|uuid|exists:users,id',
            'calendar_account_id' => 'nullable|uuid|exists:calendar_accounts,id',
            'description'         => 'nullable|string|max:2000',
            'location'            => 'nullable|string|max:200',
            'starts_at'           => 'required|date',
            'ends_at'             => 'required|date|after:starts_at',
            'is_recurring'        => 'nullable|boolean',
            'recurrence_rule'     => 'nullable|string|max:200', // RRULE:FREQ=WEEKLY;BYDAY=MO,WE
        ]);

        $event = ScheduledEvent::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
            'status'    => 'scheduled',
        ]);

        // Auto-push to CalDAV if account linked
        if ($event->calendar_account_id) {
            $this->calDav->pushEvent($event);
        }

        return response()->json($event->load('course', 'coach'), 201);
    }

    // PATCH /api/v1/scheduling/events/{event}
    public function updateEvent(Request $request, ScheduledEvent $event): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'description' => 'sometimes|nullable|string|max:2000',
            'location'    => 'sometimes|nullable|string|max:200',
            'starts_at'   => 'sometimes|date',
            'ends_at'     => 'sometimes|date',
            'status'      => 'sometimes|in:scheduled,cancelled,completed',
            'coach_id'    => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        $event->update($data);

        // Re-sync to CalDAV if linked
        if ($event->calendar_account_id) {
            $this->calDav->pushEvent($event->fresh());
        }

        return response()->json($event->fresh('course', 'coach'));
    }

    // DELETE /api/v1/scheduling/events/{event}
    public function deleteEvent(ScheduledEvent $event): JsonResponse
    {
        if ($event->calendar_account_id) {
            $this->calDav->deleteEvent($event);
        }

        $event->delete();

        return response()->json(null, 204);
    }

    // ── Calendar accounts ─────────────────────────────────────────

    // GET /api/v1/scheduling/calendars
    public function calendars(): JsonResponse
    {
        $accounts = CalendarAccount::all();

        return response()->json($accounts);
    }

    // POST /api/v1/scheduling/calendars
    public function createCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'caldav_url'    => 'required|url',
            'username'      => 'required|string|max:100',
            'password'      => 'required|string|max:200',
            'calendar_path' => 'nullable|string|max:500',
            'color'         => 'nullable|string|max:7',
        ]);

        $account = CalendarAccount::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
        ]);

        return response()->json($account, 201);
    }

    // POST /api/v1/scheduling/calendars/{account}/sync
    public function syncCalendar(Request $request, CalendarAccount $account): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->format('Ymd\THis\Z');
        $to   = $data['to']   ?? now()->addMonths(3)->format('Ymd\THis\Z');

        $synced = $this->calDav->syncFromCalDav($account, $from, $to);

        return response()->json([
            'message'       => "Sync complete.",
            'events_synced' => $synced,
            'synced_at'     => now(),
        ]);
    }

    // POST /api/v1/scheduling/events/{event}/push-to-calendar
    public function pushToCalendar(ScheduledEvent $event): JsonResponse
    {
        if (! $event->calendar_account_id) {
            return response()->json(['message' => 'No calendar account linked to this event.'], 422);
        }

        $success = $this->calDav->pushEvent($event);

        return response()->json([
            'message' => $success ? 'Event pushed to calendar.' : 'Push failed — check calendar account credentials.',
            'success' => $success,
        ]);
    }
}
