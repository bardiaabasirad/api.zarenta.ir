<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderWallet;

class AssetController extends Controller
{
    public function assets()
    {
        $groupedAssets = MetalTraderWallet::query()
            ->where('metal_trader_wallets.metal_trader_id', auth()->id())
            ->leftJoin('metal_items', 'metal_trader_wallets.metal_item_id', '=', 'metal_items.id')
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScope('visible')
                        ->select(['id', 'title', 'unit', 'is_spot', 'metal_item_group_id', 'sort_order']);
                }
            ])
            ->select('metal_trader_wallets.*')
            // همان سورت دقیق دیتابیسی
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
                // تعیین کلید گروه‌بندی
                return (is_null($wallet->metal_item_id) || $wallet->metalItem?->is_spot)
                    ? 'spot_and_cash'
                    : 'forward_items';
            });

        return response()->json($groupedAssets);
    }
}
