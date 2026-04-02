<?php

namespace App\Jobs;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOverdueInvoiceReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

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
                'student'  => $student,
                'invoice'  => $this->invoice,
                'tenant'   => $tenant,
                'balance'  => $this->invoice->balance_due,
                'due_date' => $this->invoice->due_date->format('d/m/Y'),
            ],
            function ($message) use ($student, $tenant) {
                $message->to($student->email, $student->full_name)
                        ->subject("[{$tenant->name}] Payment reminder — Invoice #{$this->invoice->number}");
            }
        );

        $this->invoice->update(['notified_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Failed to send overdue invoice reminder', [
            'invoice_id' => $this->invoice->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
