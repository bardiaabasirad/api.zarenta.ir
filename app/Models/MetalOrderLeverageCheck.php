<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalOrderLeverageCheck extends Model
{
    protected $guarded = ['id'];

    public function metalOrder()
    {
        return $this->belongsTo(MetalOrder::class);
    }
}
