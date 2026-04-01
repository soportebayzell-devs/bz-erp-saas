<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use App\Modules\Students\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'assigned_to',
        'student_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'source',
        'status',
        'interest_level',
        'preferred_course_type',
        'notes',
        'contacted_at',
        'qualified_at',
        'converted_at',
        'lost_reason',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    // -----------------------------------------------------------
    // Global scope — always filter by tenant
    // -----------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($builder) {
            if (app()->bound('tenant_id')) {
                $builder->where('leads.tenant_id', app('tenant_id'));
            }
        });
    }

    // -----------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getIsConvertedAttribute(): bool
    {
        return $this->status === 'converted' && ! is_null($this->converted_at);
    }

    // -----------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(LeadActivity::class)->latestOfMany();
    }

    // -----------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeStale($query, int $days = 7)
    {
        return $query->where('status', '!=', 'converted')
                     ->where('status', '!=', 'lost')
                     ->where('updated_at', '<', now()->subDays($days));
    }
}
