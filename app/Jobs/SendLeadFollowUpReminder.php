<?php

namespace App\Jobs;

use App\Modules\CRM\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendLeadFollowUpReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly Lead $lead
    ) {}

    public function handle(): void
    {
        $assignedUser = $this->lead->assignedTo;

        if (! $assignedUser || ! $assignedUser->email) {
            return;
        }

        Mail::send(
            'emails.lead.followup_reminder',
            [
                'user'       => $assignedUser,
                'lead'       => $this->lead,
                'tenant'     => $this->lead->tenant,
                'stale_days' => now()->diffInDays($this->lead->updated_at),
            ],
            function ($message) use ($assignedUser) {
                $message->to($assignedUser->email, $assignedUser->name)
                        ->subject("Follow-up needed: {$this->lead->full_name}");
            }
        );
    }
}
