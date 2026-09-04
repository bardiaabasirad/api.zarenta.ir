<?php

namespace App\Models;

use App\Casts\ActiveInactiveCast;
use App\Models\Configs\DealingGroupMetalItemConfig;
use Illuminate\Database\Eloquent\Model;

class DealingGroup extends Model
{
    protected $guarded = ['id'];

    public function metalItems()
    {
        return $this->belongsToMany(MetalItem::class, 'dealing_group_metal_item')
            ->using(DealingGroupMetalItemConfig::class)
            ->withPivot([
                'tolerance_type',
                'display_mode',
                'min_order',
                'max_order',
                'buy_fee_margin',
                'sell_fee_margin'
            ])
            ->withTimestamps();
    }

    /** Reverse helper: همه کانفیگ‌های فلزات مربوط به این گروه */
    public function metalItemConfigs()
    {
        return $this->hasMany(DealingGroupMetalItemConfig::class, 'dealing_group_id');
    }

    public function metalTraders()
    {
        return $this->hasMany(MetalTrader::class, 'dealing_group_id');
    }
}
