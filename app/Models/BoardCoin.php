<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
