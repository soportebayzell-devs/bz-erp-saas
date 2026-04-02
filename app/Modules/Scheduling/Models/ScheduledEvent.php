<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledEvent extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'coach_id', 'calendar_account_id',
        'title', 'description', 'location', 'type',
        'starts_at', 'ends_at', 'is_recurring', 'recurrence_rule',
        'status', 'caldav_uid', 'caldav_etag', 'caldav_data', 'caldav_synced_at',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'is_recurring'     => 'boolean',
        'caldav_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('scheduled_events.tenant_id', app('tenant_id'));
            }
        });
    }

    public function course()
    {
        return $this->belongsTo(\App\Modules\Courses\Models\Course::class);
    }

    public function coach()
    {
        return $this->belongsTo(\App\Models\User::class, 'coach_id');
    }

    public function calendarAccount()
    {
        return $this->belongsTo(CalendarAccount::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('starts_at', [$from, $to]);
    }
}
