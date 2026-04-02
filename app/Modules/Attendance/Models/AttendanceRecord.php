<?php

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id', 'attendee_type', 'attendee_id',
        'status', 'check_in_method', 'checked_in_at', 'notes',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function attendee()
    {
        // Polymorphic — returns Student or User depending on attendee_type
        if ($this->attendee_type === 'student') {
            return $this->belongsTo(\App\Modules\Students\Models\Student::class, 'attendee_id');
        }
        return $this->belongsTo(\App\Models\User::class, 'attendee_id');
    }
}
