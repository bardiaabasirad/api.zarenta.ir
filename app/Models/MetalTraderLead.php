<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalTraderLead extends Model
{
    protected $fillable = [
        'phone',
        'last_attempt_at',
        'lead_type'
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
    ];
}
