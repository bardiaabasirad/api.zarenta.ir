<?php

namespace App\Observers;

use App\Events\MetalTraderUpdated;
use App\Models\DealingGroup;
use App\Models\MetalTrader;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class MetalTraderObserver
{
    public function updated(MetalTrader $metalTrader): void
    {
        $metalTrader->load('dealingGroup.metalItemConfigs');
        $metalTrader->inquiry_access = $metalTrader->subscriptionNotExpired('inquiry');

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

        event(new MetalTraderUpdated($metalTrader));
    }
}
