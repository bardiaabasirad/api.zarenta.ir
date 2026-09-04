<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

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
}
