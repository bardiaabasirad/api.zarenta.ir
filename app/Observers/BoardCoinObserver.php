<?php

namespace App\Observers;

use App\Events\BoardMetalPriceUpdated;
use App\Models\BoardCoin;
use App\Services\BoardCoinService;
use Illuminate\Support\Facades\Cache;

class BoardCoinObserver
{
    public function saved(BoardCoin $boardCoin): void
    {
        Cache::forget('board_coin_metal_ids');

        $this->broadcastBoard();
    }

    public function deleted(BoardCoin $boardCoin): void
    {
        Cache::forget('board_coin_metal_ids');

        $this->broadcastBoard();
    }

    private function broadcastBoard()
    {
        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

        BoardMetalPriceUpdated::dispatch($coins);
    }
}
