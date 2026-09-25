<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalItemGroup;
use App\Models\Setting;
use App\Services\EncryptionService;
use Illuminate\Support\Facades\Redis;

class RateController extends Controller
{
    public function getRate()
    {
        // همه تنظیمات موردنیاز را فقط یک بار بخوان
        $allSettingKeys = [
            'market_status',
            'validity_period_of_melted_order_before_expires'
        ];

        $settings = Setting::whereIn('option_key', $allSettingKeys)
            ->get()
            ->pluck('option_value', 'option_key')
            ->toArray();

        $metalItemGroups = MetalItemGroup::with(['metalItems' => function ($query) {
            $query->orderBy('sort_order', 'asc');
        }, 'metalItems.latestPrice'])->orderBy('sort_order', 'asc')->get();

        $metalItemGroups = EncryptionService::encrypt($metalItemGroups);

        return response()->json([
            'metal_item_groups' => $metalItemGroups,
            'market_status' => $settings['market_status'] ?? null,
            'expiration_time' => $settings['validity_period_of_melted_order_before_expires'] ?? null,
            'messages' => Redis::get('client:messages'),
        ]);
    }
}
