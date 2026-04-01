<?php

// ═══════════════════════════════════════════════════════════════
// Command: MarkOverdueInvoices
// Runs daily — stamps past-due pending invoices as 'overdue'
// ═══════════════════════════════════════════════════════════════
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

        // Queue email reminders for newly overdue invoices
        $overdueInvoices = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->with(['student', 'tenant'])
            ->where('status', 'overdue')
            ->whereNull('notified_at')
            ->get();

        foreach ($overdueInvoices as $invoice) {
            // Bind tenant context before dispatching per-tenant jobs
            \App\Jobs\SendOverdueInvoiceReminder::dispatch($invoice);
        }

        $this->info("Queued {$overdueInvoices->count()} overdue reminder(s).");

        return Command::SUCCESS;
    }
}


// ═══════════════════════════════════════════════════════════════
// Command: SendFollowUpReminders
// Runs daily — finds stale leads and queues follow-up nudges
// ═══════════════════════════════════════════════════════════════
namespace App\Console\Commands;

use App\Modules\CRM\Models\Lead;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature   = 'erp:send-followup-reminders {--days=7 : Days without activity}';
    protected $description = 'Queue follow-up reminders for stale leads.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Remove global tenant scope — this runs across all tenants
        $staleLeads = Lead::withoutGlobalScope('tenant')
            ->with(['tenant', 'assignedTo'])
            ->stale($days)
            ->get();

        foreach ($staleLeads as $lead) {
            \App\Jobs\SendLeadFollowUpReminder::dispatch($lead);
        }

        $this->info("Queued {$staleLeads->count()} follow-up reminder(s) for leads stale > {$days} days.");

        return Command::SUCCESS;
    }
}
