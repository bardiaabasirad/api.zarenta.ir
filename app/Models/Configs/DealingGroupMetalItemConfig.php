<?php
namespace App\Models\Configs;

use App\Models\MetalItem;
use App\Models\DealingGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DealingGroupMetalItemConfig extends Pivot
{
    protected $table = 'dealing_group_metal_item';

    protected $fillable = [
        'dealing_group_id',
        'metal_item_id',
        'tolerance_type',
        'display_mode',
        'min_order',
        'max_order',
        'buy_fee_margin',
        'sell_fee_margin',
    ];

    protected $casts = [
        'min_order' => 'float',
        'max_order' => 'float',
        'buy_fee_margin' => 'float',
        'sell_fee_margin' => 'float',
    ];

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function dealingGroup(): BelongsTo
    {
        return $this->belongsTo(DealingGroup::class);
    }
}
