<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Staff\Models\LeaveRequest;
use App\Modules\Staff\Models\SalaryPayment;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffService $staffService
    ) {}

    // GET /api/v1/staff
    public function index(Request $request): JsonResponse
    {
        $staff = StaffProfile::with('user:id,name,email,role,is_active,phone')
            ->when($request->department, fn ($q) => $q->where('department', $request->department))
            ->when($request->type,       fn ($q) => $q->where('employment_type', $request->type))
            ->paginate($request->per_page ?? 25);

        return response()->json($staff);
    }

    // GET /api/v1/staff/summary
    public function summary(): JsonResponse
    {
        return response()->json($this->staffService->getSummary());
    }

    // POST /api/v1/staff
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'                  => 'required|uuid|exists:users,id',
            'position'                 => 'nullable|string|max:100',
            'department'               => 'nullable|string|max:100',
            'employment_type'          => 'nullable|in:full_time,part_time,contractor',
            'salary'                   => 'nullable|numeric|min:0',
            'salary_period'            => 'nullable|in:monthly,weekly,hourly',
            'hire_date'                => 'nullable|date',
            'nit'                      => 'nullable|string|max:30',
            'igss_number'              => 'nullable|string|max:30',
            'bank_account'             => 'nullable|string|max:50',
            'emergency_contact_name'   => 'nullable|string|max:100',
            'emergency_contact_phone'  => 'nullable|string|max:30',
            'notes'                    => 'nullable|string|max:2000',
        ]);

        $user    = User::findOrFail($data['user_id']);
        $profile = $this->staffService->createProfile($user, $data);

        return response()->json($profile->load('user'), 201);
    }

    // GET /api/v1/staff/{profile}
    public function show(StaffProfile $profile): JsonResponse
    {
        return response()->json(
            $profile->load(['user', 'leaveRequests' => fn ($q) => $q->latest()->limit(10), 'salaryPayments' => fn ($q) => $q->latest()->limit(6)])
        );
    }

    // PATCH /api/v1/staff/{profile}
    public function update(Request $request, StaffProfile $profile): JsonResponse
    {
        $data = $request->validate([
            'position'                => 'sometimes|nullable|string|max:100',
            'department'              => 'sometimes|nullable|string|max:100',
            'employment_type'         => 'sometimes|in:full_time,part_time,contractor',
            'salary'                  => 'sometimes|nullable|numeric|min:0',
            'salary_period'           => 'sometimes|in:monthly,weekly,hourly',
            'hire_date'               => 'sometimes|nullable|date',
            'termination_date'        => 'sometimes|nullable|date',
            'nit'                     => 'sometimes|nullable|string|max:30',
            'igss_number'             => 'sometimes|nullable|string|max:30',
            'bank_account'            => 'sometimes|nullable|string|max:50',
            'emergency_contact_name'  => 'sometimes|nullable|string|max:100',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:30',
            'notes'                   => 'sometimes|nullable|string|max:2000',
        ]);

        $profile->update($data);

        return response()->json($profile->fresh('user'));
    }

    // ── Leave requests ────────────────────────────────────────────

    // GET /api/v1/staff/leave
    public function leaveIndex(Request $request): JsonResponse
    {
        $leaves = LeaveRequest::with('user:id,name,email')
            ->when($request->status,  fn ($q) => $q->where('status', $request->status))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->orderBy('starts_on', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($leaves);
    }

    // POST /api/v1/staff/leave
    public function requestLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'   => 'required|uuid|exists:users,id',
            'type'      => ['required', Rule::in(['vacation','sick','personal','unpaid','other'])],
            'starts_on' => 'required|date',
            'ends_on'   => 'required|date|after_or_equal:starts_on',
            'reason'    => 'nullable|string|max:1000',
        ]);

        $leave = $this->staffService->requestLeave($data);

        return response()->json($leave->load('user'), 201);
    }

    // PATCH /api/v1/staff/leave/{leave}/approve
    public function approveLeave(LeaveRequest $leave): JsonResponse
    {
        $leave = $this->staffService->approveLeave($leave, auth()->id());

        return response()->json($leave);
    }

    // PATCH /api/v1/staff/leave/{leave}/reject
    public function rejectLeave(Request $request, LeaveRequest $leave): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $leave = $this->staffService->rejectLeave($leave, $data['reason']);

        return response()->json($leave);
    }

    // ── Salary ────────────────────────────────────────────────────

    // POST /api/v1/staff/salary/generate
    public function generateSalary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'     => 'required|uuid|exists:users,id',
            'period'      => 'required|string|regex:/^\d{4}-\d{2}$/', // 2025-03
            'base_amount' => 'nullable|numeric|min:0',
            'bonuses'     => 'nullable|numeric|min:0',
            'deductions'  => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:500',
        ]);

        $user    = User::findOrFail($data['user_id']);
        $payment = $this->staffService->generateSalaryPayment($user, $data['period'], $data);

        return response()->json($payment->load('user'), 201);
    }

    // PATCH /api/v1/staff/salary/{payment}/pay
    public function recordSalaryPayment(Request $request, SalaryPayment $payment): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash','bank_transfer','check','other'])],
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        $payment = $this->staffService->recordSalaryPayment($payment, $data);

        return response()->json($payment);
    }
}
