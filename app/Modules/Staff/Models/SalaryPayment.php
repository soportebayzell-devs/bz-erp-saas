<?php

namespace App\Modules\Staff\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'base_amount', 'bonuses',
        'deductions', 'net_amount', 'currency', 'period',
        'status', 'payment_method', 'reference', 'notes', 'paid_at',
    ];

    protected $casts = [
        'base_amount' => 'float',
        'bonuses'     => 'float',
        'deductions'  => 'float',
        'net_amount'  => 'float',
        'paid_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('salary_payments.tenant_id', app('tenant_id'));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
