<?php

namespace App\Services;

use App\Models\SelectedMetalPrice;
use App\Models\PriceSourceMapping;
use App\Models\RawMetalPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Reverb\Loggers\Log;

class SelectedMetalPriceService
{
    // Cache برای جلوگیری از query های تکراری در یک request
    private static array $processedItems = [];

    public static function createRate(
        $rawMetalPrice,
        $priceSourceMapping,
        array $variables,
        bool $useCurrentTime = false
    ): void
    {
        if ($priceSourceMapping) {
            $buy = $rawMetalPrice->buy ? roundUpToThousand(calculateFormula(str_replace('fee', 'buy', $priceSourceMapping->buy), $variables)) : null;
            $sell = $rawMetalPrice->sell ? roundUpToThousand(calculateFormula(str_replace('fee', 'sell', $priceSourceMapping->sell), $variables)) : null;

            if ($priceSourceMapping->generate_buy_or_sell == 'active' && (!$buy || !$sell)) {
                if (!$buy && $priceSourceMapping->buy_from_sell) {
                    $buy = roundUpToThousand(calculateFormula(str_replace('fee', 'sell', $priceSourceMapping->buy_from_sell), $variables));
                }
                if (!$sell && $priceSourceMapping->sell_from_buy) {
                    $sell = roundUpToThousand(calculateFormula(str_replace('fee', 'buy', $priceSourceMapping->sell_from_buy), $variables));
                }
            }
        } else {
            $buy = $rawMetalPrice->buy;
            $sell = $rawMetalPrice->sell;
        }

        SelectedMetalPrice::create([
            'price_source_id' => $priceSourceMapping ? $priceSourceMapping->price_source_id : $rawMetalPrice->price_source_id,
            'metal_item_id' => $rawMetalPrice->metal_item_id,
            'buy' => $buy,
            'sell' => $sell,
            'time' => $useCurrentTime ? now() : $rawMetalPrice->time
        ]);

        Cache::forget("latest_price_{$rawMetalPrice->metal_item_id}");
    }

    public static function handleNewPrice(array $item): void
    {
        // 1. ابتدا سعی می‌کنیم قیمت خام را ثبت کنیم
        $rawPrice = self::setRawMetalPrice($item);

        // 2. بررسی موفقیت: اگر null نبود یعنی عملیات دیتابیس انجام شده
        if ($rawPrice) {

            if (! PriceSourceMapping::where('price_source_id', $item['price_source_id'])->where('metal_item_id', $item['metal_item_id'])->exists()) {
                return;
            }

            // اگر قبلاً پردازش نشده، processNewPrice را صدا بزن
            if (!isset(self::$processedItems[$item['metal_item_id']])) {
                self::processNewPrice($item);
                self::$processedItems[$item['metal_item_id']] = true;
            }
        } else {
            // لاگ کردن در صورت خطا
            Log::error("Failed to save RawMetalPrice for ID: " . $item['metal_item_id']);
        }
    }


    private static function setRawMetalPrice(array $item): ?RawMetalPrice
    {
        // استفاده از firstOrCreate نتیجه را به صورت یک Instance برمی‌گرداند
        return RawMetalPrice::firstOrCreate(
            [
                'metal_item_id' => $item['metal_item_id'],
                'price_source_id' => $item['price_source_id'],
                'buy' => $item['buy'],
                'sell' => $item['sell'],
                'time' => Carbon::parse($item['time'])->timezone('Asia/Tehran'),
            ]
        );
    }

    private static function processNewPrice($item): void
    {
        // یکبار latest rate را بگیریم
        $latestPrice = Cache::remember(
            "latest_price_{$item['metal_item_id']}",
            60,
            fn() => SelectedMetalPrice::where('metal_item_id', $item['metal_item_id'])
                ->latest()
                ->first(['id', 'price_source_id', 'metal_item_id', 'created_at', 'time'])
        );

        if (!$latestPrice) {
            // Cache reference markets
            $priceSourceMapping = Cache::remember(
                "price_source_mapping_{$item['metal_item_id']}",
                now()->addDays(30),
                fn() => PriceSourceMapping::where('metal_item_id', $item['metal_item_id'])
                    ->first()
            );

            if ($priceSourceMapping) {
                // استفاده از Redis Lock به جای MySQL Advisory Lock (سریعتر)
                $lock = Cache::lock("price_processing_{$priceSourceMapping->id}", 10);

                try {
                    if ($lock->get()) {
                        DB::transaction(function () use ($item, $priceSourceMapping) {
                            self::processRate($priceSourceMapping, $item['metal_item_id']);
                        });
                    }
                } finally {
                    optional($lock)->release();
                }
            }
        } elseif ($latestPrice && $latestPrice->price_source_id != null) {
            // Cache reference markets
            $priceSourceMapping = Cache::remember(
                "price_source_mapping_{$item['metal_item_id']}",
                now()->addDays(30),
                fn() => PriceSourceMapping::where('metal_item_id', $item['metal_item_id'])
                    ->first()
            );

            if ($priceSourceMapping) {
                // استفاده از Redis Lock به جای MySQL Advisory Lock (سریعتر)
                $lock = Cache::lock("price_processing_{$priceSourceMapping->id}", 10);

                try {
                    if ($lock->get()) {
                        DB::transaction(function () use ($item, $latestPrice, $priceSourceMapping) {
                            self::processRate($priceSourceMapping, $item['metal_item_id'], $latestPrice);
                        });
                    }
                } finally {
                    optional($lock)->release();
                }
            }
        }
    }

    private static function processRate($priceSourceMapping, $metal_item_id, $latestPrice = null): void
    {
        $rawMetalPrice = RawMetalPrice::where('metal_item_id', $metal_item_id)
            ->where('price_source_id', $priceSourceMapping->price_source_id)
            ->latest()
            ->first();

        if ($latestPrice && $latestPrice->time == $rawMetalPrice->time) {
            return;
        }

        if (!$latestPrice || $latestPrice->time != $rawMetalPrice->time) {
            $vars = ['buy' => $rawMetalPrice->buy, 'sell' => $rawMetalPrice->sell];

            // ارسال $appConstantType به عنوان پارامتر جداگانه
            self::createRate(
                $rawMetalPrice,
                $priceSourceMapping,
                $vars
            );
        }

    }

    // متد برای پاکسازی cache های static در پایان request
    public static function resetRequestCache(): void
    {
        self::$processedItems = [];
    }

    public static function getLatestRawPriceFromSelectedSource(): \Illuminate\Support\Collection
    {
        return DB::table('raw_metal_prices as rmp')
            ->join('price_source_mappings as psm', function ($join) {
                $join->on('rmp.price_source_id', '=', 'psm.price_source_id')
                    ->on('rmp.metal_item_id', '=', 'psm.metal_item_id');
            })
            ->join('metal_items as mi', 'rmp.metal_item_id', '=', 'mi.id')
            ->join('price_sources as ps', 'rmp.price_source_id', '=', 'ps.id')
            ->whereIn('rmp.id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('raw_metal_prices')
                    ->groupBy('metal_item_id', 'price_source_id');
            })
            ->select(
                'rmp.*',
                'mi.title as metal_title',
                'ps.name as source_name'
            )
            ->get();
    }

    public static function regenerateRateFromReferenceMarket($priceSourceMapping)
    {
        $rawMetalPrice = RawMetalPrice::latest()
            ->where('metal_item_id', $priceSourceMapping->metal_item_id)
            ->where('price_source_id', $priceSourceMapping->price_source_id)
            ->first();

        if ($rawMetalPrice) {
            $variables = ['buy' => $rawMetalPrice->buy, 'sell' => $rawMetalPrice->sell];

            self::createRate(
                $rawMetalPrice,
                $priceSourceMapping,
                $variables,
                true
            );
        }
    }
}
