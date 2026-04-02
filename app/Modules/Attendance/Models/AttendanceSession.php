<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Courses\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSession extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'coach_id', 'title', 'type',
        'scheduled_at', 'started_at', 'ended_at', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('attendance_sessions.tenant_id', app('tenant_id'));
            }
        });
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function presentRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id')
                    ->where('status', 'present');
    }

    public function getAttendanceRateAttribute(): float
    {
        $total = $this->records()->count();
        if ($total === 0) return 0;
        $present = $this->records()->whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 1);
    }
}
