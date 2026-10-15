<?php

namespace App\Models;

use App\Observers\PriceSourceMappingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([PriceSourceMappingObserver::class])]
class PriceSourceMapping extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'generate_buy_or_sell' => 'boolean',
    ];

    public function priceSource()
    {
        return $this->belongsTo(PriceSource::class);
    }

    public function setGenerateBuyOrSellAttribute($value)
    {
        $this->attributes['generate_buy_or_sell'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function selectedMetalPrice(): HasMany
    {
        return $this->hasMany(SelectedMetalPrice::class, 'price_source_id', 'price_source_id')
            ->where('selected_metal_prices.type', '=', $this->type);
    }
}
