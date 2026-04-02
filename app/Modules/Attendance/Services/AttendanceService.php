<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Courses\Models\Course;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    // ── Create a session ──────────────────────────────────────────

    public function createSession(array $data): AttendanceSession
    {
        $session = AttendanceSession::create([
            ...$data,
            'tenant_id' => app('tenant_id'),
        ]);

        // If linked to a course, auto-populate expected attendees
        if (! empty($data['course_id']) && ($data['auto_populate'] ?? true)) {
            $this->populateFromCourse($session);
        }

        return $session->load('course', 'coach', 'records');
    }

    // ── Auto-populate records from course enrollment ───────────────

    public function populateFromCourse(AttendanceSession $session): void
    {
        $course = Course::find($session->course_id);
        if (! $course) return;

        $enrolledStudentIds = $course->activeStudents()
            ->pluck('student_id');

        $records = $enrolledStudentIds->map(fn ($studentId) => [
            'id'             => \Illuminate\Support\Str::uuid(),
            'session_id'     => $session->id,
            'attendee_type'  => 'student',
            'attendee_id'    => $studentId,
            'status'         => 'absent', // default until checked in
            'check_in_method'=> 'manual',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        AttendanceRecord::insert($records->toArray());
    }

    // ── Check in a single attendee ────────────────────────────────

    public function checkIn(
        AttendanceSession $session,
        string $attendeeType,
        string $attendeeId,
        string $status = 'present',
        string $method = 'manual'
    ): AttendanceRecord {
        return AttendanceRecord::updateOrCreate(
            [
                'session_id'    => $session->id,
                'attendee_type' => $attendeeType,
                'attendee_id'   => $attendeeId,
            ],
            [
                'status'          => $status,
                'check_in_method' => $method,
                'checked_in_at'   => now(),
            ]
        );
    }

    // ── Bulk check-in (mark whole class) ──────────────────────────

    public function bulkUpdate(AttendanceSession $session, array $records): void
    {
        // $records = [['attendee_id' => uuid, 'attendee_type' => 'student', 'status' => 'present'], ...]
        DB::transaction(function () use ($session, $records) {
            foreach ($records as $record) {
                AttendanceRecord::updateOrCreate(
                    [
                        'session_id'    => $session->id,
                        'attendee_type' => $record['attendee_type'],
                        'attendee_id'   => $record['attendee_id'],
                    ],
                    [
                        'status'        => $record['status'],
                        'checked_in_at' => in_array($record['status'], ['present', 'late']) ? now() : null,
                        'notes'         => $record['notes'] ?? null,
                    ]
                );
            }
        });
    }

    // ── Attendance report for a student ───────────────────────────

    public function studentReport(string $studentId, ?string $courseId = null, ?int $days = 30): array
    {
        $query = AttendanceRecord::query()
            ->where('attendee_type', 'student')
            ->where('attendee_id', $studentId)
            ->whereHas('session', function ($q) use ($courseId, $days) {
                $q->where('tenant_id', app('tenant_id'));
                if ($courseId) $q->where('course_id', $courseId);
                if ($days) $q->where('scheduled_at', '>=', now()->subDays($days));
            })
            ->with('session:id,title,scheduled_at,course_id');

        $records = $query->get();
        $total   = $records->count();

        return [
            'total_sessions' => $total,
            'present'        => $records->where('status', 'present')->count(),
            'absent'         => $records->where('status', 'absent')->count(),
            'late'           => $records->where('status', 'late')->count(),
            'excused'        => $records->where('status', 'excused')->count(),
            'attendance_rate' => $total > 0
                ? round($records->whereIn('status', ['present', 'late'])->count() / $total * 100, 1)
                : 0,
            'records'        => $records,
        ];
    }
}
