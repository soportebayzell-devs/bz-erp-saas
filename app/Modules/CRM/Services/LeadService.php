<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Students\Models\Student;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    // -----------------------------------------------------------
    // Create
    // -----------------------------------------------------------

    public function create(array $data): Lead
    {
        $lead = Lead::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
            'status'    => 'new',
        ]);

        $this->logActivity($lead, 'created', ['source' => $data['source'] ?? 'manual']);

        return $lead->load('assignedTo');
    }

    // -----------------------------------------------------------
    // Status transitions
    // -----------------------------------------------------------

    public function updateStatus(Lead $lead, string $newStatus, ?string $notes = null): Lead
    {
        $previousStatus = $lead->status;

        $timestamps = match ($newStatus) {
            'contacted' => ['contacted_at' => now()],
            'qualified'  => ['qualified_at' => now()],
            default      => [],
        };

        $lead->update(['status' => $newStatus, ...$timestamps]);

        $this->logActivity($lead, 'status_change', [
            'from'  => $previousStatus,
            'to'    => $newStatus,
            'notes' => $notes,
        ]);

        return $lead->fresh();
    }

    public function markLost(Lead $lead, string $reason): Lead
    {
        $lead->update([
            'status'      => 'lost',
            'lost_reason' => $reason,
        ]);

        $this->logActivity($lead, 'status_change', [
            'from'   => $lead->getOriginal('status'),
            'to'     => 'lost',
            'reason' => $reason,
        ]);

        return $lead->fresh();
    }

    // -----------------------------------------------------------
    // Lead → Student conversion  (the critical workflow)
    // -----------------------------------------------------------

    /**
     * Converts a qualified lead into a Student record.
     * Optionally enrolls them in a course and generates an invoice.
     *
     * @param  Lead   $lead
     * @param  array  $data  {
     *   course_id?: string,
     *   generate_invoice?: bool,
     *   due_date?: string,
     *   discount?: float,
     *   notes?: string,
     * }
     * @return Student
     */
    public function convert(Lead $lead, array $data = []): Student
    {
        if ($lead->is_converted) {
            throw new \LogicException("Lead [{$lead->id}] is already converted.");
        }

        return DB::transaction(function () use ($lead, $data) {

            // 1. Create student record — preserve all lead data
            $student = Student::create([
                'tenant_id'   => $lead->tenant_id,
                'lead_id'     => $lead->id,
                'first_name'  => $lead->first_name,
                'last_name'   => $lead->last_name,
                'email'       => $lead->email,
                'phone'       => $lead->phone,
                'status'      => 'active',
                'enrolled_at' => now(),
            ]);

            // 2. Stamp the lead as converted
            $lead->update([
                'status'       => 'converted',
                'student_id'   => $student->id,
                'converted_at' => now(),
            ]);

            // 3. Log the conversion activity
            $this->logActivity($lead, 'converted', [
                'student_id' => $student->id,
            ]);

            // 4. Optional: enroll in a course
            if (! empty($data['course_id'])) {
                $student->enrollments()->create([
                    'course_id'   => $data['course_id'],
                    'status'      => 'active',
                    'enrolled_at' => now(),
                    'notes'       => $data['notes'] ?? null,
                ]);

                // 5. Optional: auto-generate invoice for the enrollment
                if ($data['generate_invoice'] ?? false) {
                    $this->invoiceService->generateForEnrollment($student, $data);
                }
            }

            return $student->load('enrollments.course');
        });
    }

    // -----------------------------------------------------------
    // Activity logging
    // -----------------------------------------------------------

    public function logActivity(Lead $lead, string $type, array $metadata = []): LeadActivity
    {
        return LeadActivity::create([
            'lead_id'  => $lead->id,
            'user_id'  => auth()->id(),
            'type'     => $type,
            'notes'    => $metadata['notes'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    // -----------------------------------------------------------
    // Assign
    // -----------------------------------------------------------

    public function assign(Lead $lead, string $userId): Lead
    {
        $lead->update(['assigned_to' => $userId]);

        $this->logActivity($lead, 'assigned', ['assigned_to' => $userId]);

        return $lead->fresh('assignedTo');
    }
}
