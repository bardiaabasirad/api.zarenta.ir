<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\MarketPrice;
use App\Models\PersianCoin;

class PersianCoinController extends Controller
{
    public function all()
    {
        return response()->json([
            'persian_coins' => PersianCoin::orderBy('weight')->get(),
            'gold_price' => MarketPrice::latest()->first(),
        ]);
    }
}
