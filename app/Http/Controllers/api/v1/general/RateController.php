<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\MetalCard;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RateController extends Controller
{

    public function getAggregatedRates()
    {
        $moltenPageMetalItem = Cache::rememberForever(
            'setting.molten_page_metal_item_id',
            fn() => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
        );

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $prices = DB::table('selected_metal_prices')
            ->where('metal_item_id', $moltenPageMetalItem->option_value)
            ->whereBetween('time', [$start, $end])
            ->selectRaw('
                DATE_FORMAT(time, "%Y-%m-%d %H:%i") as minute,
                ROUND(AVG(sell), 2) as avg_sell,
                MIN(sell) as min_sell,
                MAX(sell) as max_sell
            ')
            ->groupBy('minute')
            ->orderBy('minute')
            ->get();

        return response()->json($prices);
    }

    public function domesticMarketLanding()
    {
        $moltenPageMetalItem = Cache::rememberForever(
            'setting.molten_page_metal_item_id',
            fn() => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
        );

        // 1. دریافت تمام تنظیمات مورد نیاز در یک کوئری (جلوگیری از کوئری‌های پراکنده)
        $settings = Setting::whereIn('option_key', [
            'melted_gold_validity_period',
            'market_status',
        ])->pluck('option_value', 'option_key');

        // 2. دریافت نرخ فردا (استفاده از Query Builder تمیزتر)
        $latestPrice = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
            ->where('sell', '>', 0)
            ->with('priceSource')
            ->latest()
            ->first();

        // 4. منطق دریافت نرخ دیروز
        $yesterdayRate = null;
        // فرض بر این است که created_at در مدل به Carbon کست شده است
        if ($latestPrice && $latestPrice->created_at->isToday()) {
            $yesterdayRate = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->whereDate('created_at', Carbon::yesterday())
                ->where('sell', '>', 0)
                ->latest()
                ->first();
        }

        // 5. کوئری نمودار (ایمن‌سازی شده با Binding)
        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        // استفاده از ? بجای متغیر مستقیم برای امنیت کامل
        $rawSubQuery = '
            (
                SELECT
                    DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS bucket,
                    sell,
                    AVG(sell) OVER (PARTITION BY DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")) AS avg_sell,
                    MIN(sell) OVER (PARTITION BY DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")) AS min_sell,
                    MAX(sell) OVER (PARTITION BY DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")) AS max_sell,
                    COUNT(sell) OVER (PARTITION BY DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")) AS cnt,
                    FIRST_VALUE(sell) OVER (
                        PARTITION BY DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")
                        ORDER BY created_at DESC
                    ) AS last_sell
                FROM selected_metal_prices
                WHERE created_at BETWEEN ? AND ?
                    AND sell IS NOT NULL
                    AND metal_item_id = ?
            ) as t
        ';

        $prices = DB::table(DB::raw($rawSubQuery))
            ->setBindings([$start, $end, $moltenPageMetalItem->option_value]) // بایندینگ پارامترها
            ->select('bucket', 'avg_sell', 'min_sell', 'max_sell', 'last_sell', 'cnt')
            ->groupBy('bucket', 'avg_sell', 'min_sell', 'max_sell', 'last_sell', 'cnt')
            ->orderBy('bucket')
            ->get();

        // 6. مپ کردن خروجی
        $chartData = $prices->map(function ($r) {
            // ایجاد آبجکت کربن یکبار برای استفاده در هر دو فیلد
            $date = Carbon::createFromFormat('Y-m-d H:i', $r->bucket);

            return [
                'bucket'    => $date->format('H:i'),
                'avg'       => (float) $r->avg_sell,
                'min'       => (float) $r->min_sell,
                'max'       => (float) $r->max_sell,
                'last'      => (float) $r->last_sell,
                'count'     => (int) $r->cnt,
                'timestamp' => $date->toIso8601String(),
            ];
        });

        // 7. بازگشت پاسخ JSON
        return response()->json([
            'rate'              => $latestPrice,
            'market_status'     => $settings->get('market_status'), // خواندن از کالکشن کش شده
            'yesterday_rate'    => $yesterdayRate,
            'melted_gold_cards' => MetalCard::orderBy('order')->get(),
            'chart_data'        => $chartData,
            'melted_gold_validity_period' => $settings->get('melted_gold_validity_period'),
        ]);
    }
    public function lastOnes($price = null)
    {
        $moltenPageMetalItem = Cache::rememberForever(
            'setting.molten_page_metal_item_id',
            fn() => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
        );

        $price = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
            ->with('priceSource')
            ->find($price);

        $resetYesterday = false;

        if($price && Carbon::parse($price->created_at)->isYesterday()){
            $resetYesterday = true;
            $prices = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->latest()
                ->where('id', '>', $price->id)
                ->whereNotNull('sell')
                ->where('sell', '>', 0)
                ->select('id', 'sell', 'time', 'created_at', 'updated_at')
                ->get();

            $prices = $prices->map(function ($items) {
                return [
                    'xAxis' => Carbon::parse($items->created_at)->format('H:i'),
                    'yAxisSell' => $items->sell
                ];
            });
        }
        else if ($price){
            $prices = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->latest()
                ->where('id', '>', $price->id)
                ->whereNotNull('sell')
                ->where('sell', '>', 0)
                ->select('id', 'sell', 'time', 'created_at', 'updated_at')
                ->get();

            $price = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->latest()
                ->with('priceSource')
                ->where('id', '>', $price->id)
                ->whereNotNull('sell')
                ->where('sell', '>', 0)
                ->select('id', 'buy', 'sell', 'time', 'created_at', 'updated_at', 'reference_channel_id')
                ->first();

            if($price && Carbon::parse($price->created_at)->isToday()){
                $yesterday_rate = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)->whereDate('created_at', Carbon::yesterday())
                    ->latest()
                    ->first();
            }

            $prices = $prices->map(function ($items) {
                return [
                    'xAxis' => Carbon::parse($items->created_at)->format('H:i'),
                    'yAxisSell' => $items->sell
                ];
            });
        }
        else{
            $price = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->with('priceSource')
                ->latest()
                ->whereNotNull('sell')
                ->where('sell', '>', 0)
                ->first();

            if($price && Carbon::parse($price->created_at)->isToday()){
                $yesterday_rate = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                    ->latest()
                    ->whereDate('created_at', Carbon::yesterday())
                    ->whereNotNull('sell')
                    ->where('sell', '>', 0)
                    ->first();
            }

            $prices = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
                ->latest()
                ->whereNotNull('sell')
                ->where('sell', '>', 0)
                ->select('id', 'sell', 'time', 'created_at', 'updated_at')
                ->whereDate('created_at', Carbon::today())
                ->get()
                ->map(function ($items) {
                    return [
                        'xAxis' => Carbon::parse($items->created_at)->format('H:i'),
                        'yAxisSell' => $items->sell
                    ];
                });
        }

        return response()->json([
            'rate' => $price??null,
            'market_status' => Setting::where('option_key','market_status')->first()->option_value,
            'yesterday_rate' => $yesterday_rate??null,
            'chart_data' => $prices->reverse()->values(),
            'reset_yesterday' => $resetYesterday,
        ]);
    }

    public function domesticMarket()
    {
        $moltenPageMetalItem = Cache::rememberForever(
            'setting.molten_page_metal_item_id',
            fn() => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
        );

        $price = SelectedMetalPrice::where('metal_item_id', $moltenPageMetalItem->option_value)
            ->latest()
            ->whereNotNull('sell')
            ->where('sell', '>', 0)
            ->first();

        return response()->json($price->sell);
    }
}
