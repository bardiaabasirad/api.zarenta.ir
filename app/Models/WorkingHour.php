<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'working_hours',
    ];

    protected $casts = [
        'working_hours' => 'array'
    ];
}
