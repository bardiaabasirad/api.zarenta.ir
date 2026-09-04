<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalItemAutoOrderSetting extends Model
{
    protected $fillable = [
        'metal_item_id',
        'price_source_id',
        'is_enabled',
        'min_auto_buy_weight',
        'max_auto_buy_weight',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'min_auto_buy_weight' => 'decimal:3',
        'max_auto_buy_weight' => 'decimal:3',
    ];

    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function priceSource()
    {
        return $this->belongsTo(PriceSource::class);
    }
}
