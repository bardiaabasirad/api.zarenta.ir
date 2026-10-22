<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkingHour extends Model
{
    use HasFactory;

    protected $primaryKey = 'day_of_week';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'day_of_week',
        'working_hours',
        'order',
    ];

    protected $casts = [
        'working_hours' => 'array',
    ];
}
