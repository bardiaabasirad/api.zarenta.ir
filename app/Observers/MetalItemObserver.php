<?php

namespace App\Observers;

use App\Events\BoardMetalPriceUpdated;
use App\Events\MetalItemUpdated;
use App\Models\BoardCoin;
use App\Models\MetalItem;
use App\Services\BoardCoinService;
use Illuminate\Support\Facades\Cache;

class MetalItemObserver
{
    public function saved(MetalItem $metalItem): void
    {
        MetalItemUpdated::dispatch($metalItem);

        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

        BoardMetalPriceUpdated::dispatch($coins);
    }
}
