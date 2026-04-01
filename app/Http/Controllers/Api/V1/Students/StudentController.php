<?php

namespace App\Http\Controllers\Api\V1\Students;

use App\Http\Controllers\Controller;
use App\Modules\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    // GET /api/v1/students
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'    => 'nullable|string',
            'search'    => 'nullable|string|max:100',
            'course_id' => 'nullable|uuid',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $students = Student::query()
            ->with(['activeEnrollments.course:id,name,type'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->course_id, fn ($q) =>
                $q->whereHas('enrollments', fn ($eq) =>
                    $eq->where('course_id', $request->course_id)
                       ->where('status', 'active')
                )
            )
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q2) =>
                    $q2->whereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$s}%"])
                       ->orWhere('email', 'ILIKE', "%{$s}%")
                       ->orWhere('phone', 'ILIKE', "%{$s}%")
                );
            })
            ->orderBy('first_name')
            ->paginate($request->per_page ?? 25);

        return response()->json($students);
    }

    // GET /api/v1/students/{student}
    public function show(Student $student): JsonResponse
    {
        return response()->json(
            $student->load([
                'lead',
                'enrollments.course',
                'invoices' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
            ])
        );
    }

    // POST /api/v1/students  (manual creation without lead conversion)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|max:255',
            'phone'         => 'nullable|string|max:30',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality'   => 'nullable|string|max:100',
            'nit'           => 'nullable|string|max:30',
            'notes'         => 'nullable|string|max:2000',
        ]);

        // Email uniqueness within tenant is enforced at DB level,
        // but we give a friendly error here
        $exists = Student::where('email', $data['email'])->exists();
        if ($exists) {
            return response()->json([
                'message' => 'A student with this email already exists.',
                'errors'  => ['email' => ['Email is already registered for this academy.']],
            ], 422);
        }

        $student = Student::create([
            ...$data,
            'tenant_id'   => app('tenant_id'),
            'status'      => 'active',
            'enrolled_at' => now(),
        ]);

        return response()->json($student, 201);
    }

    // PATCH /api/v1/students/{student}
    public function update(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'first_name'    => 'sometimes|string|max:100',
            'last_name'     => 'sometimes|string|max:100',
            'email'         => 'sometimes|email|max:255',
            'phone'         => 'sometimes|nullable|string|max:30',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'nationality'   => 'sometimes|nullable|string|max:100',
            'nit'           => 'sometimes|nullable|string|max:30',
            'notes'         => 'sometimes|nullable|string|max:2000',
        ]);

        $student->update($data);

        return response()->json($student->fresh());
    }

    // PATCH /api/v1/students/{student}/status
    public function updateStatus(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'graduated', 'dropped'])],
            'notes'  => 'nullable|string|max:500',
        ]);

        $timestamps = match ($data['status']) {
            'graduated' => ['graduated_at' => now()],
            'dropped'   => ['dropped_at'   => now()],
            default     => [],
        };

        $student->update(['status' => $data['status'], ...$timestamps]);

        return response()->json($student->fresh());
    }

    // POST /api/v1/students/{student}/enroll
    public function enroll(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'notes'     => 'nullable|string|max:500',
        ]);

        // Prevent double enrollment
        $alreadyEnrolled = $student->enrollments()
            ->where('course_id', $data['course_id'])
            ->where('status', 'active')
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'message' => 'Student is already enrolled in this course.',
            ], 422);
        }

        $enrollment = $student->enrollments()->create([
            'course_id'   => $data['course_id'],
            'status'      => 'active',
            'enrolled_at' => now(),
            'notes'       => $data['notes'] ?? null,
        ]);

        return response()->json($enrollment->load('course'), 201);
    }

    // GET /api/v1/students/{student}/invoices
    public function invoices(Student $student): JsonResponse
    {
        $invoices = $student->invoices()
            ->with('items', 'payments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($invoices);
    }

    // DELETE /api/v1/students/{student}
    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(null, 204);
    }
}
