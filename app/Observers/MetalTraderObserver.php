<?php

namespace App\Observers;

use App\Events\MetalTraderUpdated;
use App\Models\DealingGroup;
use App\Models\MetalTrader;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetalTraderObserver
{
    public function updated(MetalTrader $metalTrader): void
    {
        $metalTrader->load('dealingGroup.metalItemConfigs');

        if (! $metalTrader->dealingGroup) {
            $setting = Cache::rememberForever(
                'setting.default_metal_trader_group_id',
                fn() => Setting::firstWhere('option_key', 'default_metal_trader_group_id')
            );

            if ($setting) {
                $defaultDealingGroup = DealingGroup::with('metalItemConfigs')->find($setting->option_value);

                if ($defaultDealingGroup) {
                    $metalTrader->setRelation('dealingGroup', $defaultDealingGroup);
                }
            }
        }

        try {
            event(new MetalTraderUpdated($metalTrader));
        } catch (Throwable $e) {
            // Auth/API must not fail when Reverb/Pusher is down or misconfigured.
            Log::error('MetalTraderUpdated broadcast failed', [
                'metal_trader_id' => $metalTrader->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
