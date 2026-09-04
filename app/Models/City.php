<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function shippingMethod()
    {
        return $this->belongsToMany(ShippingMethod::class, 'city_shipping_methods')->withPivot('shipping_cost','status');
    }
}
