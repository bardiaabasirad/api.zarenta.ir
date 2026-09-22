<?php

namespace App\Observers;

use App\Events\MarketPriceChanged;
use App\Models\MarketPrice;

class MarketPriceObserver
{
    public function created(MarketPrice $marketPrice): void
    {
        MarketPriceChanged::dispatch($marketPrice);
    }
}
