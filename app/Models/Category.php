<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['title','image'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            $imagePath = $model->image;
            if (Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
        });
    }

    public function sections() {
        return $this->morphMany(Section::class, 'sectionable');
    }
}
