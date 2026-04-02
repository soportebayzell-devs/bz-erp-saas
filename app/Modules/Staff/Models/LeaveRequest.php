<?php

namespace App\Modules\Staff\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'approved_by', 'type',
        'starts_on', 'ends_on', 'days', 'status',
        'reason', 'rejection_reason', 'approved_at',
    ];

    protected $casts = [
        'starts_on'   => 'date',
        'ends_on'     => 'date',
        'approved_at' => 'datetime',
        'days'        => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('leave_requests.tenant_id', app('tenant_id'));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
