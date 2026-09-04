<?php

namespace App\Services;

use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Models\SelectedAutoOrderExchange;
use App\Models\RawMetalPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoGoldOrderService
{
    private const PRODUCT_MAP = [
        'tomorrow_spot_settlement'           => ['market_type' => 'tomorrow',               'type' => 'tomorrow',           'delivery' => 'آبشده نقد فردا'],
        'day_after_tomorrow_spot_settlement' => ['market_type' => 'day_after_tomorrow',     'type' => 'day_after_tomorrow', 'delivery' => 'آبشده نقد پس فردا'],
        'gold_coin_86'                       => ['market_type' => 'gold_coin_86',           'type' => 'coins',              'delivery' => 'سکه 86'],
        'gold_half_coin_86'                  => ['market_type' => 'gold_half_coin_86',      'type' => 'coins',              'delivery' => 'نیم سکه 86'],
        'gold_quarter_coin_86'               => ['market_type' => 'gold_quarter_coin_86',   'type' => 'coins',              'delivery' => 'ربع سکه 86'],
        'gold_coin_old_version'              => ['market_type' => 'gold_coin_old_version',  'type' => 'coins',              'delivery' => 'سکه قدیم'],
    ];

    public static function prepareForAutoOrder(MetalOrder $goldOrder)
    {
        $lockKey = "auto_order_{$goldOrder->id}";

        // تلاش برای گرفتن لاک 5 ثانیه‌ای
        $lock = Cache::lock($lockKey, 5);

        if (!$lock->get()) {
            Log::info('AutoGoldOrderService::prepareForAutoOrder skipped because lock exists', [
                'order_id' => $goldOrder->id,
            ]);
            return null; // یعنی هم‌زمان یه نفر دیگه در حال ثبتش بوده
        }

        $productConfig = static::resolveProductConfig($goldOrder->product['name'] ?? null);

        if (empty($productConfig)) {
            return collect();
        }

        $quantity = $goldOrder->product['quantity'] ?? null;

        if (! is_numeric($quantity)) {
            return collect();
        }

        $triedReferences = MetalOrderExchange::where('gold_order_id', $goldOrder->id)
            ->get()
            ->pluck('reference_channel_id');

        $autoOrders = SelectedAutoOrderExchange::query()
            ->select('reference_channel_id', 'validity_period')
            ->where([
                ['status', '=', 'active'],
                ['type', '=', $productConfig['type']],
            ])
            ->whereNotIn('reference_channel_id', $triedReferences)
            ->where('min', '<=', $quantity)
            ->where('max', '>=', $quantity)
            ->get()
            ->keyBy('reference_channel_id');

        if ($autoOrders->isEmpty()) {
            return collect();
        }

        $latestPrices = RawMetalPrice::query()
            ->select('reference_channel_id', 'buy', 'sell', 'created_at', 'time') // انتخاب فیلدهای ضروری
            ->where('type', $productConfig['market_type'])
            ->whereIn('reference_channel_id', $autoOrders->keys())
            ->orderByDesc('time')
            ->get()
            ->unique('reference_channel_id');

        $rates = $latestPrices
            ->filter(fn ($price) => static::isPriceWithinValidity($price, $autoOrders[$price->reference_channel_id]))
            ->values();

        if ($rates->isEmpty()) {
            return collect();
        }

        $ratesCollection = collect($rates);

        $bestRate = $goldOrder->order_type === 'buy'
            ? $ratesCollection->sortBy('sell')->first()
            : $ratesCollection->sortByDesc('buy')->first();

        MetalOrderExchange::create([
            'gold_order_id' => $goldOrder->id,
            'reference_channel_id' => $bestRate->reference_channel_id,
        ]);
    }

    private static function resolveProductConfig(?string $productName): array
    {
        return self::PRODUCT_MAP[$productName] ?? [];
    }

    private static function isPriceWithinValidity(RawMetalPrice $price, SelectedAutoOrderExchange $autoOrder): bool
    {
        $validitySeconds = (int) $autoOrder->validity_period;

        if ($validitySeconds <= 0) {
            return false;
        }

        return $price->created_at
            ->copy()
            ->addSeconds($validitySeconds)
            ->isFuture();
    }
}
