<?php

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
