<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityShippingMethod extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
