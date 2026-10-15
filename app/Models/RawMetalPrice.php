<?php

namespace App\Models;

use App\Observers\RawMetalPriceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RawMetalPriceObserver::class])]
class RawMetalPrice extends Model
{
    protected $table = 'raw_metal_prices';
    protected $guarded = ['id'];

    protected $dates = ['created_at', 'updated_at', 'time'];

    public function priceSource(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class, 'price_source_id');
    }

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class, 'metal_item_id');
    }
}
