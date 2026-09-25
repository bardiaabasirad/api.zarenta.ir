<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalTraderWallet extends Model
{
    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class, 'metal_item_id');
    }

    public function trader()
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function getIsFiatAttribute(): bool
    {
        return is_null($this->metal_item_id);
    }
}
