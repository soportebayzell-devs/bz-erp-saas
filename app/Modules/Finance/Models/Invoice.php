<?php

namespace App\Modules\Finance\Models;

use App\Modules\Students\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','student_id','number','status','subtotal',
        'tax','discount','total','currency','due_date','paid_at',
        'notes','notified_at',
    ];

    protected $casts = [
        'subtotal'     => 'float',
        'tax'          => 'float',
        'discount'     => 'float',
        'total'        => 'float',
        'due_date'     => 'date',
        'paid_at'      => 'datetime',
        'notified_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('invoices.tenant_id', app('tenant_id'));
            }
        });
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, $this->total - $this->total_paid);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', now());
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
