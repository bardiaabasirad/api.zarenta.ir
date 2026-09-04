<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['title','tracking_url'];

    public function cities()
    {
        return $this->belongsToMany(City::class, 'city_shipping_methods');
    }
}
