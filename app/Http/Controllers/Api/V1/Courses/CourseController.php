<?php

namespace App\Http\Controllers\Api\V1\Courses;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    // GET /api/v1/courses
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'nullable|in:online,in_person,hybrid',
            'status'   => 'nullable|in:draft,active,archived',
            'coach_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $courses = Course::query()
            ->withCount(['enrollments as enrolled_count' => fn ($q) => $q->where('status', 'active')])
            ->with('coach:id,name,email')
            ->when($request->type,     fn ($q) => $q->where('type', $request->type))
            ->when($request->status,   fn ($q) => $q->where('status', $request->status))
            ->when($request->coach_id, fn ($q) => $q->where('coach_id', $request->coach_id))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json($courses);
    }

    // POST /api/v1/courses
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:200',
            'type'                 => ['required', Rule::in(['online', 'in_person', 'hybrid'])],
            'level'                => 'nullable|in:beginner,intermediate,advanced',
            'description'          => 'nullable|string|max:5000',
            'capacity'             => 'required|integer|min:1|max:500',
            'price'                => 'required|numeric|min:0',
            'currency'             => 'nullable|string|size:3',
            'coach_id'             => 'nullable|uuid|exists:users,id',
            'schedule_description' => 'nullable|string|max:500',
            'starts_at'            => 'nullable|date',
            'ends_at'              => 'nullable|date|after:starts_at',
            'status'               => 'nullable|in:draft,active,archived',
        ]);

        $course = Course::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
            'currency'  => $data['currency'] ?? app('tenant')->currency,
            'status'    => $data['status'] ?? 'active',
        ]);

        return response()->json($course->load('coach'), 201);
    }

    // GET /api/v1/courses/{course}
    public function show(Course $course): JsonResponse
    {
        return response()->json(
            $course->load([
                'coach:id,name,email',
                'enrollments.student:id,first_name,last_name,email,status',
            ])->append('remaining_capacity')
        );
    }

    // PATCH /api/v1/courses/{course}
    public function update(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'name'                 => 'sometimes|string|max:200',
            'type'                 => ['sometimes', Rule::in(['online', 'in_person', 'hybrid'])],
            'level'                => 'sometimes|nullable|in:beginner,intermediate,advanced',
            'description'          => 'sometimes|nullable|string|max:5000',
            'capacity'             => 'sometimes|integer|min:1|max:500',
            'price'                => 'sometimes|numeric|min:0',
            'coach_id'             => 'sometimes|nullable|uuid|exists:users,id',
            'schedule_description' => 'sometimes|nullable|string|max:500',
            'starts_at'            => 'sometimes|nullable|date',
            'ends_at'              => 'sometimes|nullable|date',
            'status'               => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
        ]);

        // Guard: can't set capacity below current enrollment
        if (isset($data['capacity'])) {
            $activeCount = $course->activeStudents()->count();
            if ($data['capacity'] < $activeCount) {
                return response()->json([
                    'message' => "Capacity cannot be set below current enrollment ({$activeCount} students).",
                ], 422);
            }
        }

        $course->update($data);

        return response()->json($course->fresh('coach'));
    }

    // GET /api/v1/courses/{course}/students
    public function students(Course $course): JsonResponse
    {
        $students = $course->enrollments()
            ->where('status', 'active')
            ->with('student:id,first_name,last_name,email,phone,status')
            ->get()
            ->pluck('student');

        return response()->json([
            'course'   => $course->only('id', 'name', 'capacity'),
            'enrolled' => $students->count(),
            'students' => $students,
        ]);
    }

    // DELETE /api/v1/courses/{course}
    public function destroy(Course $course): JsonResponse
    {
        $activeEnrollments = $course->activeStudents()->count();

        if ($activeEnrollments > 0) {
            return response()->json([
                'message' => "Cannot delete a course with {$activeEnrollments} active student(s).",
            ], 422);
        }

        $course->delete();

        return response()->json(null, 204);
    }
}
