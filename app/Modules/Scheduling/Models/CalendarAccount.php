<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CalendarAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'provider',
        'caldav_url', 'username', 'password', 'calendar_path',
        'color', 'is_active', 'last_synced_at', 'sync_error',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('calendar_accounts.tenant_id', app('tenant_id'));
            }
        });
    }

    public function events()
    {
        return $this->hasMany(ScheduledEvent::class);
    }
}
