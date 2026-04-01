<?php

// ═══════════════════════════════════════════════════════════════
// Job: SendOverdueInvoiceReminder
// ═══════════════════════════════════════════════════════════════
namespace App\Jobs;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOverdueInvoiceReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(
        private readonly Invoice $invoice
    ) {}

    public function handle(): void
    {
        $student = $this->invoice->student;
        $tenant  = $this->invoice->tenant;

        if (! $student || ! $student->email) {
            return;
        }

        Mail::send(
            'emails.invoice.overdue',
            [
                'student'    => $student,
                'invoice'    => $this->invoice,
                'tenant'     => $tenant,
                'balance'    => $this->invoice->balance_due,
                'due_date'   => $this->invoice->due_date->format('d/m/Y'),
                'portal_url' => "https://{$tenant->slug}.yourdomain.com/portal/invoices/{$this->invoice->id}",
            ],
            function ($message) use ($student, $tenant) {
                $message->to($student->email, $student->full_name)
                        ->subject("[{$tenant->name}] Payment reminder — Invoice #{$this->invoice->number}");
            }
        );

        // Mark as notified so we don't spam
        $this->invoice->update(['notified_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('Failed to send overdue invoice reminder', [
            'invoice_id' => $this->invoice->id,
            'error'      => $e->getMessage(),
        ]);
    }
}


// ═══════════════════════════════════════════════════════════════
// Job: SendLeadFollowUpReminder
// ═══════════════════════════════════════════════════════════════
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
            return; // No one to notify
        }

        Mail::send(
            'emails.lead.followup_reminder',
            [
                'user'      => $assignedUser,
                'lead'      => $this->lead,
                'tenant'    => $this->lead->tenant,
                'stale_days'=> now()->diffInDays($this->lead->updated_at),
                'crm_url'   => "https://{$this->lead->tenant->slug}.yourdomain.com/leads/{$this->lead->id}",
            ],
            function ($message) use ($assignedUser) {
                $message->to($assignedUser->email, $assignedUser->name)
                        ->subject("Follow-up needed: {$this->lead->full_name}");
            }
        );
    }
}
