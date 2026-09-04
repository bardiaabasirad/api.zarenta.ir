<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    public function varieties()
    {
        return $this->hasMany(Variety::class)->orderBy('weight');
    }

    public function variety()
    {
        return $this->hasOne(Variety::class);
    }

    public function imageVariety()
    {
        return $this->hasOne(Variety::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function directories()
    {
        return $this->belongsToMany(Directory::class);
    }

    public function properties()
    {
        return $this->belongsToMany(Property::class)
            ->using(PropertyValuePivot::class)
            ->withPivot('values');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function image()
    {
        return $this->hasOne(ProductImage::class)->latest();
    }

    public function size_unit()
    {
        return $this->belongsTo(SizeUnit::class);
    }
}
