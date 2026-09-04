<?php

namespace App\Models;

use App\Models\Configs\DealingGroupMetalItemConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configs\MetalItemPriceSource;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MetalItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_buy_active' => 'boolean',
        'is_sell_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(MetalItemGroup::class, 'metal_item_group_id');
    }

    public function dealingGroups()
    {
        return $this->belongsToMany(DealingGroup::class, 'dealing_group_metal_item')
            ->using(DealingGroupMetalItemConfig::class)
            ->withPivot([
                'tolerance_type',
                'min_order',
                'max_order',
                'buy_fee_margin',
                'sell_fee_margin'
            ])
            ->withTimestamps();
    }

    /** Reverse helper: تمام کانفیگ‌ها برای این فلز */
    public function dealingGroupConfigs()
    {
        return $this->hasMany(DealingGroupMetalItemConfig::class, 'metal_item_id');
    }

    public function autoOrderSetting()
    {
        return $this->hasOne(MetalItemAutoOrderSetting::class);
    }

    /**
     * رابطه many-to-many با PriceSource از طریق جدول pivot
     */
    public function priceSources(): BelongsToMany
    {
        return $this->belongsToMany(
            related: PriceSource::class,
            table: 'metal_item_price_source',
            foreignPivotKey: 'metal_item_id',
            relatedPivotKey: 'price_source_id',
        )->using(MetalItemPriceSource::class)
            ->withPivot('rate_external_identifier')
            ->withTimestamps();
    }

    public function selectedMetalPrices()
    {
        return $this->hasMany(SelectedMetalPrice::class, 'metal_item_id');
    }

    public function priceSourceMapping()
    {
        return $this->hasOne(PriceSourceMapping::class, 'metal_item_id');
    }

    public function latestPrice()
    {
        return $this->hasOne(SelectedMetalPrice::class, 'metal_item_id')
            ->select('selected_metal_prices.id', 'selected_metal_prices.price_source_id', 'selected_metal_prices.metal_item_id', 'selected_metal_prices.buy', 'selected_metal_prices.sell', 'selected_metal_prices.created_at')
            ->latestOfMany();
    }

    public function previousDayPrice()
    {
        return $this->hasOne(SelectedMetalPrice::class, 'metal_item_id')
            ->select('selected_metal_prices.id', 'selected_metal_prices.price_source_id', 'selected_metal_prices.metal_item_id', 'selected_metal_prices.buy', 'selected_metal_prices.sell', 'selected_metal_prices.created_at')
            ->ofMany(
                ['id' => 'max'],
                fn ($query) => $query->whereDate('created_at', today()->subDay())
            );
    }

    protected static function booted(): void
    {
        static::addGlobalScope('visible', function (Builder $query) {
            $query->where('is_visible', true);
        });
    }

    // MetalItem.php
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('visible')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
