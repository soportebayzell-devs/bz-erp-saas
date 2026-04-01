<?php

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService
    ) {}

    // GET /api/v1/leads
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => 'nullable|string',
            'assigned_to' => 'nullable|uuid',
            'source'      => 'nullable|string',
            'search'      => 'nullable|string|max:100',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $leads = Lead::query()
            ->with(['assignedTo:id,name,email', 'latestActivity'])
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->assigned_to, fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->source,      fn ($q) => $q->where('source', $request->source))
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q2) =>
                    $q2->whereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$s}%"])
                       ->orWhere('email', 'ILIKE', "%{$s}%")
                       ->orWhere('phone', 'ILIKE', "%{$s}%")
                );
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($leads);
    }

    // POST /api/v1/leads
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'            => 'required|string|max:100',
            'last_name'             => 'required|string|max:100',
            'email'                 => 'nullable|email|max:255',
            'phone'                 => 'nullable|string|max:30',
            'source'                => 'nullable|string|max:50',
            'interest_level'        => 'nullable|in:low,medium,high',
            'preferred_course_type' => 'nullable|in:online,in_person,hybrid',
            'notes'                 => 'nullable|string|max:2000',
            'assigned_to'           => 'nullable|uuid|exists:users,id',
        ]);

        $lead = $this->leadService->create($data);

        return response()->json($lead->load('assignedTo'), 201);
    }

    // GET /api/v1/leads/{lead}
    public function show(Lead $lead): JsonResponse
    {
        return response()->json(
            $lead->load(['assignedTo', 'activities.user:id,name', 'student'])
        );
    }

    // PATCH /api/v1/leads/{lead}
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'first_name'            => 'sometimes|string|max:100',
            'last_name'             => 'sometimes|string|max:100',
            'email'                 => 'sometimes|nullable|email',
            'phone'                 => 'sometimes|nullable|string|max:30',
            'source'                => 'sometimes|nullable|string|max:50',
            'interest_level'        => 'sometimes|nullable|in:low,medium,high',
            'preferred_course_type' => 'sometimes|nullable|in:online,in_person,hybrid',
            'notes'                 => 'sometimes|nullable|string|max:2000',
            'assigned_to'           => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        $lead->update($data);

        return response()->json($lead->fresh('assignedTo'));
    }

    // PATCH /api/v1/leads/{lead}/status
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new','contacted','qualified','lost'])],
            'notes'  => 'nullable|string|max:1000',
            'reason' => 'nullable|required_if:status,lost|string|max:500',
        ]);

        if ($data['status'] === 'lost') {
            $lead = $this->leadService->markLost($lead, $data['reason']);
        } else {
            $lead = $this->leadService->updateStatus($lead, $data['status'], $data['notes'] ?? null);
        }

        return response()->json($lead);
    }

    // POST /api/v1/leads/{lead}/convert
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'course_id'        => 'nullable|uuid|exists:courses,id',
            'generate_invoice' => 'boolean',
            'due_date'         => 'nullable|date|after:today',
            'discount'         => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $student = $this->leadService->convert($lead, $data);

        return response()->json([
            'message' => 'Lead successfully converted to student.',
            'student' => $student,
        ], 201);
    }

    // POST /api/v1/leads/{lead}/activities
    public function addActivity(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'type'         => ['required', Rule::in(['call','email','note','whatsapp'])],
            'notes'        => 'required|string|max:2000',
            'scheduled_at' => 'nullable|date',
        ]);

        $activity = $this->leadService->logActivity($lead, $data['type'], $data);

        return response()->json($activity, 201);
    }

    // DELETE /api/v1/leads/{lead}
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(null, 204);
    }
}
