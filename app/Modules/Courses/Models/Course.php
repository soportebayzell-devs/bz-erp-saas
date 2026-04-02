<?php

namespace App\Modules\Courses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','coach_id','name','type','level','description',
        'capacity','price','currency','status','schedule_description',
        'starts_at','ends_at',
    ];

    protected $casts = [
        'price'     => 'float',
        'capacity'  => 'integer',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('courses.tenant_id', app('tenant_id'));
            }
        });
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeStudents()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    public function coach()
    {
        return $this->belongsTo(\App\Models\User::class, 'coach_id');
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->activeStudents()->count());
    }
}
