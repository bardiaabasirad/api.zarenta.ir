<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VarietyLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'new_values' => 'array',
        'old_values' => 'array',
        'details' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'log_by');
    }
}
