<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'timezone',
        'currency',
        'logo_url',
        'primary_color',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'settings'  => 'array',
        'is_active' => 'boolean',
    ];

    // -----------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------

    /**
     * Check if a feature flag is enabled for this tenant.
     * Usage: $tenant->featureEnabled('automation')
     */
    public function featureEnabled(string $feature): bool
    {
        return (bool) data_get($this->settings, "features.{$feature}", true);
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    // -----------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(\App\Modules\CRM\Models\Lead::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(\App\Modules\Students\Models\Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(\App\Modules\Courses\Models\Course::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Finance\Models\Invoice::class);
    }
}
