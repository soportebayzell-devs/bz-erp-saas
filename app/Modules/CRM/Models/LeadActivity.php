<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    use HasUuids;

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'notes',
        'metadata',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
