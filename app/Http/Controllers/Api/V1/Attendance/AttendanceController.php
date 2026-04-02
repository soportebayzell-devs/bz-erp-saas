<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {}

    // GET /api/v1/attendance/sessions
    public function sessions(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'nullable|uuid',
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $sessions = AttendanceSession::query()
            ->with(['course:id,name', 'coach:id,name'])
            ->withCount('records', 'presentRecords')
            ->when($request->course_id, fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->from, fn ($q) => $q->where('scheduled_at', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->where('scheduled_at', '<=', $request->to))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($sessions);
    }

    // POST /api/v1/attendance/sessions
    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'type'          => 'nullable|in:class,staff_meeting,event',
            'course_id'     => 'nullable|uuid|exists:courses,id',
            'coach_id'      => 'nullable|uuid|exists:users,id',
            'scheduled_at'  => 'required|date',
            'notes'         => 'nullable|string|max:1000',
            'auto_populate' => 'nullable|boolean',
        ]);

        $session = $this->attendanceService->createSession($data);

        return response()->json($session, 201);
    }

    // GET /api/v1/attendance/sessions/{session}
    public function showSession(AttendanceSession $session): JsonResponse
    {
        return response()->json(
            $session->load(['course', 'coach', 'records'])
                    ->append('attendance_rate')
        );
    }

    // POST /api/v1/attendance/sessions/{session}/check-in
    public function checkIn(Request $request, AttendanceSession $session): JsonResponse
    {
        $data = $request->validate([
            'attendee_type' => 'required|in:student,staff',
            'attendee_id'   => 'required|uuid',
            'status'        => 'nullable|in:present,absent,late,excused',
            'method'        => 'nullable|in:manual,qr,api',
            'notes'         => 'nullable|string|max:500',
        ]);

        $record = $this->attendanceService->checkIn(
            $session,
            $data['attendee_type'],
            $data['attendee_id'],
            $data['status'] ?? 'present',
            $data['method'] ?? 'manual'
        );

        return response()->json($record, 201);
    }

    // POST /api/v1/attendance/sessions/{session}/bulk
    public function bulkUpdate(Request $request, AttendanceSession $session): JsonResponse
    {
        $data = $request->validate([
            'records'                  => 'required|array|min:1',
            'records.*.attendee_type'  => 'required|in:student,staff',
            'records.*.attendee_id'    => 'required|uuid',
            'records.*.status'         => ['required', Rule::in(['present','absent','late','excused'])],
            'records.*.notes'          => 'nullable|string|max:500',
        ]);

        $this->attendanceService->bulkUpdate($session, $data['records']);

        return response()->json([
            'message' => 'Attendance updated.',
            'session' => $session->load('records')->append('attendance_rate'),
        ]);
    }

    // GET /api/v1/attendance/students/{studentId}/report
    public function studentReport(Request $request, string $studentId): JsonResponse
    {
        $request->validate([
            'course_id' => 'nullable|uuid',
            'days'      => 'nullable|integer|min:1|max:365',
        ]);

        $report = $this->attendanceService->studentReport(
            $studentId,
            $request->course_id,
            $request->days ?? 30
        );

        return response()->json($report);
    }
}
