<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slider extends Model
{
    protected $guarded = ['id'];

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class, 'slider_id', 'id');
    }
}
