<?php

namespace App\Models;

use App\Observers\BoardCoinObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([BoardCoinObserver::class])]
class BoardCoin extends Model
{
    protected $fillable = [
        'metal_item_id',
        'sort_order',
        'buy_tolerance',
        'sell_tolerance',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class);
    }
}
