<?php

namespace App\Modules\Students\Models;

use App\Modules\Courses\Models\Enrollment;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','lead_id','first_name','last_name','email','phone',
        'date_of_birth','nationality','nit','status','avatar_url',
        'notes','enrolled_at','graduated_at','dropped_at',
    ];

    protected $casts = [
        'enrolled_at'   => 'datetime',
        'graduated_at'  => 'datetime',
        'dropped_at'    => 'datetime',
        'date_of_birth' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('students.tenant_id', app('tenant_id'));
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function pendingInvoices()
    {
        return $this->hasMany(Invoice::class)->whereIn('status', ['pending', 'partial', 'overdue']);
    }

    public function lead()
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Lead::class);
    }
}
