<?php

namespace App\Modules\Staff\Services;

use App\Models\User;
use App\Modules\Staff\Models\LeaveRequest;
use App\Modules\Staff\Models\SalaryPayment;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Support\Facades\DB;

class StaffService
{
    // ── Create staff profile linked to a user ─────────────────────

    public function createProfile(User $user, array $data): StaffProfile
    {
        return StaffProfile::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
            'user_id'   => $user->id,
        ]);
    }

    // ── Leave requests ────────────────────────────────────────────

    public function requestLeave(array $data): LeaveRequest
    {
        // Calculate business days
        $start = \Carbon\Carbon::parse($data['starts_on']);
        $end   = \Carbon\Carbon::parse($data['ends_on']);
        $days  = $start->diffInWeekdays($end) + 1;

        return LeaveRequest::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
            'days'      => $days,
            'status'    => 'pending',
        ]);
    }

    public function approveLeave(LeaveRequest $leave, string $approvedBy): LeaveRequest
    {
        $leave->update([
            'status'      => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $leave->fresh('user', 'approvedBy');
    }

    public function rejectLeave(LeaveRequest $leave, string $reason): LeaveRequest
    {
        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $leave->fresh('user');
    }

    // ── Salary payments ───────────────────────────────────────────

    public function generateSalaryPayment(User $user, string $period, array $overrides = []): SalaryPayment
    {
        $profile = StaffProfile::where('user_id', $user->id)->firstOrFail();

        $base       = $overrides['base_amount'] ?? $profile->salary ?? 0;
        $bonuses    = $overrides['bonuses'] ?? 0;
        $deductions = $overrides['deductions'] ?? 0;
        $net        = $base + $bonuses - $deductions;

        return SalaryPayment::create([
            'tenant_id'  => app('tenant_id'),
            'user_id'    => $user->id,
            'base_amount'=> $base,
            'bonuses'    => $bonuses,
            'deductions' => $deductions,
            'net_amount' => $net,
            'currency'   => $profile->currency,
            'period'     => $period,
            'status'     => 'pending',
            'notes'      => $overrides['notes'] ?? null,
        ]);
    }

    public function recordSalaryPayment(SalaryPayment $payment, array $data): SalaryPayment
    {
        $payment->update([
            'status'         => 'paid',
            'payment_method' => $data['payment_method'],
            'reference'      => $data['reference'] ?? null,
            'paid_at'        => now(),
            'notes'          => $data['notes'] ?? $payment->notes,
        ]);

        return $payment->fresh('user');
    }

    // ── Staff summary ─────────────────────────────────────────────

    public function getSummary(): array
    {
        $profiles = StaffProfile::with('user:id,name,email,role')->get();

        return [
            'total'       => $profiles->count(),
            'active'      => $profiles->filter->is_active->count(),
            'by_role'     => $profiles->groupBy('user.role')->map->count(),
            'by_type'     => $profiles->groupBy('employment_type')->map->count(),
        ];
    }
}
