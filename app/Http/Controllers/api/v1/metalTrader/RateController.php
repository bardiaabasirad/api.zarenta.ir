<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MarketHoliday;
use App\Models\MetalItemGroup;
use App\Models\Setting;
use App\Services\EncryptionService;
use Carbon\Carbon;
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
        }, 'metalItems.latestPrice'])
            ->orderBy('sort_order', 'asc')->get();

        $metalItemGroups = EncryptionService::encrypt($metalItemGroups);

        $today = Carbon::today()->toDateString();
        $thirtyDaysLater = Carbon::today()->addDays(30)->toDateString();

        $holidays = MarketHoliday::query()
            ->whereBetween('date', [$today, $thirtyDaysLater])
            ->orderBy('date', 'asc')
            ->get(['id', 'date', 'jalali_date', 'title']);

        return response()->json([
            'metal_item_groups' => $metalItemGroups,
            'holidays' => $holidays,
            'market_status' => $settings['market_status'] ?? null,
            'expiration_time' => $settings['validity_period_of_melted_order_before_expires'] ?? null,
            'messages' => Redis::get('client:messages'),
        ]);
    }
}
