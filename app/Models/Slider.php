<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Slider extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['title'];

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class, 'slider_id', 'id');
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(Section::class, 'sectionable');
    }

    /** Alias so landing-page list can use sectionable.title consistently */
    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name,
        );
    }
}
