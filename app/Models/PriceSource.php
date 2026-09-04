<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Configs\MetalItemPriceSource;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PriceSource extends Model
{
    protected $guarded = ['id'];

    public function autoOrderSettings()
    {
        return $this->hasMany(MetalItemAutoOrderSetting::class);
    }

    public function markets()
    {
        return $this->hasMany(RawMetalPrice::class, 'price_source_id');
    }

    public function market()
    {
        return $this->hasOne(RawMetalPrice::class, 'price_source_id')
            ->latestOfMany();
    }

    /**
     * رابطه معکوس many-to-many با MetalItem از طریق جدول pivot
     */
    public function metalItems(): BelongsToMany
    {
        return $this->belongsToMany(
            related: MetalItem::class,
            table: 'metal_item_price_source',
            foreignPivotKey: 'price_source_id',
            relatedPivotKey: 'metal_item_id',
        )->using(MetalItemPriceSource::class)
            ->withPivot(['rate_external_identifier', 'order_external_identifier'])
            ->withTimestamps();
    }

}
