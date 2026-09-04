<?php

namespace App\Models\Configs;

use App\Models\MetalItem;
use App\Models\PriceSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MetalItemPriceSource extends Pivot
{
    protected $table = 'metal_item_price_source';

    public $incrementing = false;

    protected $fillable = [
        'price_source_id',
        'metal_item_id',
        'rate_external_identifier',
        'order_external_identifier',
    ];

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function priceSource(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class);
    }
}
