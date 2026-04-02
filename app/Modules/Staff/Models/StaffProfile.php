<?php

namespace App\Modules\Staff\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'position', 'department',
        'employment_type', 'salary', 'salary_period', 'currency',
        'hire_date', 'termination_date', 'nit', 'igss_number',
        'bank_account', 'emergency_contact_name',
        'emergency_contact_phone', 'notes',
    ];

    protected $casts = [
        'salary'           => 'float',
        'hire_date'        => 'date',
        'termination_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('staff_profiles.tenant_id', app('tenant_id'));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'user_id', 'user_id');
    }

    public function salaryPayments()
    {
        return $this->hasMany(SalaryPayment::class, 'user_id', 'user_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->termination_date) || $this->termination_date->isFuture();
    }
}
