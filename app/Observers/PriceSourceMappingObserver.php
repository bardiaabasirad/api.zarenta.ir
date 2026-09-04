<?php

namespace App\Observers;

use App\Events\SettingsChanged;
use App\Models\PriceSourceMapping;
use App\Services\SelectedMetalPriceService;

class PriceSourceMappingObserver
{
    /**
     * Handle the PriceSourceMapping "created" event.
     */
    public function created(PriceSourceMapping $priceSourceMapping): void
    {
        SettingsChanged::dispatch();
    }

    /**
     * Handle the PriceSourceMapping "updated" event.
     */
    public function updated(PriceSourceMapping $priceSourceMapping): void
    {
        SelectedMetalPriceService::regenerateRateFromReferenceMarket($priceSourceMapping);
        SettingsChanged::dispatch();
    }

    /**
     * Handle the PriceSourceMapping "deleted" event.
     */
    public function deleted(PriceSourceMapping $priceSourceMapping): void
    {
        SettingsChanged::dispatch();
    }
}
