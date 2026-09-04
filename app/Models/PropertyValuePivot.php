<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PropertyValuePivot extends Pivot
{
    protected $casts = [
        'values' => 'array'
    ];
}
