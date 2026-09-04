<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectedAutoOrderExchange extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function priceSource()
    {
        return $this->belongsTo(PriceSource::class, 'price_source_id');
    }
}
