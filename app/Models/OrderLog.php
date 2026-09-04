<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'new_values' => 'array',
        'old_values' => 'array',
    ];

    public function loggable()
    {
        return $this->morphTo();
    }
}
