<?php

namespace App\Observers;

use App\Events\MetalOrderUpdated;
use App\Models\MetalOrderExchange;

class MetalOrderExchangeObserver
{
    public function updated(MetalOrderExchange $metalOrderExchange): void
    {
        MetalOrderUpdated::dispatch();
    }
}
