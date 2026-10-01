<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\MetalTraderWallet;
use App\Models\SelectedMetalPrice;
use Illuminate\Database\Query\Builder;

class AssetController extends Controller
{
    public function assets()
    {
        // ۱. استخراج والت‌ها به همراه اطلاعات کالا بدون هیچ Global Scope
        $groupedAssets = MetalTraderWallet::query()
            ->where('metal_trader_wallets.metal_trader_id', auth()->id())
            ->leftJoin('metal_items', 'metal_trader_wallets.metal_item_id', '=', 'metal_items.id')
            ->with([
                'metalItem' => function ($query) {
                    // غیرفعال کردن تمامی اسکوپ‌های گلوبال روی MetalItem
                    $query->withoutGlobalScopes()
                        ->select(['id', 'title', 'unit', 'is_spot', 'metal_item_group_id', 'sort_order']);
                }
            ])
            ->select('metal_trader_wallets.*')
            ->orderByRaw("
            CASE
                WHEN metal_trader_wallets.metal_item_id IS NULL THEN 1
                WHEN metal_items.is_spot = 1 THEN 2
                ELSE 3
            END ASC
        ")
            ->orderBy('metal_items.metal_item_group_id', 'asc')
            ->orderBy('metal_items.sort_order', 'asc')
            ->get()
            ->groupBy(function ($wallet) {
                return (is_null($wallet->metal_item_id) || $wallet->metalItem?->is_spot)
                    ? 'spot_and_cash'
                    : 'forward_items';
            });

        $since = now()->subDay()->startOfDay();

        // ۲. آخرین رکوردهای ثبت‌شده در جدول قیمت‌ها
        $latestByItem = SelectedMetalPrice::query()
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select('id', 'unit', 'is_spot', 'settlement_metal_item_id');
                }
            ])
            ->whereIn('id', function (Builder $query) use ($since) {
                $query->selectRaw('MAX(id)')
                    ->from('selected_metal_prices')
                    ->where('created_at', '>=', $since)
                    ->groupBy('metal_item_id');
            })
            ->get();

        // ۳. محاسبه ماکزیمم buy و مینیمم sell مربوط به اقلام Settlement
        $spotAggregates = SelectedMetalPrice::query()
            ->join('metal_items', 'selected_metal_prices.metal_item_id', '=', 'metal_items.id')
            ->whereIn('selected_metal_prices.id', function (Builder $query) use ($since) {
                $query->selectRaw('MAX(id)')
                    ->from('selected_metal_prices')
                    ->where('created_at', '>=', $since)
                    ->groupBy('metal_item_id');
            })
            ->whereNotNull('metal_items.settlement_metal_item_id')
            ->groupBy('metal_items.settlement_metal_item_id')
            ->selectRaw('
            metal_items.settlement_metal_item_id,
            MAX(selected_metal_prices.buy) as max_buy,
            MIN(selected_metal_prices.sell) as min_sell
        ')
            ->get()
            ->keyBy('settlement_metal_item_id');

        // ۴. دریافت اقلام Spot (بدون هیچ محدودیت اسکوپ و مستقیم از دیتابیس)
        $spotItems = MetalItem::query()
            ->withoutGlobalScopes()
            ->where('is_spot', true)
            ->get(['id', 'unit', 'is_spot', 'settlement_metal_item_id']);

        // ۵. ایجاد رکوردهای مجازی قیمت برای Spot
        $spotPrices = $spotItems->map(function ($item) use ($spotAggregates) {
            $agg = $spotAggregates->get($item->id);

            return (object) [
                'id' => null,
                'metal_item_id' => $item->id,
                'buy' => $agg ? $agg->max_buy : 0,
                'sell' => $agg ? $agg->min_sell : 0,
                'best_spot_buy' => $agg ? $agg->max_buy : 0,
                'metal_item' => [
                    'id' => $item->id,
                    'unit' => $item->unit,
                    'is_spot' => true,
                    'settlement_metal_item_id' => null,
                ]
            ];
        });

        // ۶. ادغام و خروجی نهایی
        $finalPrices = $latestByItem->filter(function ($price) {
            return !optional($price->metalItem)->is_spot;
        })->concat($spotPrices)->values();

        return response()->json([
            'latest_prices' => $finalPrices,
            'assets' => $groupedAssets,
        ]);
    }
}
