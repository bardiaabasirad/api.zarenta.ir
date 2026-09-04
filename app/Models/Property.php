<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = ['values','title'];

    public function getValuesAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    protected $casts = [
        'values' => 'array'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
