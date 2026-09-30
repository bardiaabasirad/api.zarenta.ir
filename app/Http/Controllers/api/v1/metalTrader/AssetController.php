<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderWallet;

class AssetController extends Controller
{
    public function assets()
    {
        $assets = MetalTraderWallet::where('metal_trader_id', auth()->id())
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScope('visible')->select(['id', 'title', 'unit']);
                }
            ])
            ->get();

        return response()->json($assets);
    }
}
