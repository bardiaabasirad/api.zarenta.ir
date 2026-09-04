<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuthLog extends Model
{
    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'guard_name',
        'event',
        'status',
        'identity',
        'ip_address',
        'user_agent',
        'session_id',
        'channel',
        'reason',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
