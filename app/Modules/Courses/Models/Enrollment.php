<?php

namespace App\Modules\Courses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id','course_id','status','enrolled_at',
        'completed_at','dropped_at','notes',
    ];

    protected $casts = [
        'enrolled_at'  => 'datetime',
        'completed_at' => 'datetime',
        'dropped_at'   => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(\App\Modules\Students\Models\Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
