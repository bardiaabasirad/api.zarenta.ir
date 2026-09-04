<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Events\NotificationsBulkRead;
use App\Models\BoardCoin;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;

class BoardCoinService
{
    public static function getCoinsWithSellPrice()
    {
        return BoardCoin::with(['metalItem.latestPrice', 'metalItem.previousDayPrice'])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($coin) {
                $price = $coin->metalItem->latestPrice;
                $prevPrice = $coin->metalItem->previousDayPrice;

                return [
                    'id'             => $coin->id,
                    'metal_item_id'  => $coin->metal_item_id,
                    'sort_order'     => $coin->sort_order,
                    'is_featured'    => $coin->is_featured,
                    'is_buy_active'  => $coin->metalItem->is_buy_active,
                    'is_sell_active' => $coin->metalItem->is_sell_active,
                    'title'          => $coin->metalItem->title,
                    'sell'           => $price->sell ? (int) $price->sell + (int) $coin->sell_tolerance : null,
                    'prev_sell'      => $prevPrice ? (int) $prevPrice->sell + (int) $coin->sell_tolerance : null,
                    'created_at'     => $price->created_at,
                    'updated_at'     => $price->updated_at,
                ];
            });
    }

    public static function getCoinsWithBuyAndSellPrices()
    {
        return BoardCoin::with('metalItem.latestPrice', 'metalItem.previousDayPrice')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($coin) {
                $price = $coin->metalItem->latestPrice;
                $prevPrice = $coin->metalItem->previousDayPrice;

                return [
                    'id'             => $coin->id,
                    'metal_item_id'  => $coin->metal_item_id,
                    'sort_order'     => $coin->sort_order,
                    'is_featured'    => $coin->is_featured,
                    'is_buy_active'  => $coin->metalItem->is_buy_active,
                    'is_sell_active' => $coin->metalItem->is_sell_active,
                    'title'          => $coin->metalItem->title,
                    'buy'            => $price->buy ? (int) $price->buy + (int) $coin->buy_tolerance : null,
                    'sell'           => $price->sell ? (int) $price->sell + (int) $coin->sell_tolerance : null,
                    'prev_buy'       => $prevPrice && $prevPrice->buy ? (int) $prevPrice->buy + (int) $coin->buy_tolerance : null,
                    'prev_sell'      => $prevPrice && $prevPrice->sell ? (int) $prevPrice->sell + (int) $coin->sell_tolerance : null,
                    'created_at'     => $price->created_at,
                    'updated_at'     => $price->updated_at,
                ];
            });
    }
}
