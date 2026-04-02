<?php

namespace App\Console\Commands;

use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature   = 'erp:mark-overdue-invoices';
    protected $description = 'Mark all past-due invoices as overdue and queue reminder notifications.';

    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->invoiceService->markOverdueInvoices();

        $this->info("Marked {$count} invoice(s) as overdue.");

        $overdueInvoices = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->with(['student', 'tenant'])
            ->where('status', 'overdue')
            ->whereNull('notified_at')
            ->get();

        foreach ($overdueInvoices as $invoice) {
            \App\Jobs\SendOverdueInvoiceReminder::dispatch($invoice);
        }

        $this->info("Queued {$overdueInvoices->count()} overdue reminder(s).");

        return Command::SUCCESS;
    }
}
