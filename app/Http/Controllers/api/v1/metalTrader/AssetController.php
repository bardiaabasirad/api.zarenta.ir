<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderWallet;
use App\Services\SettingsService;

class AssetController extends Controller
{
    public function assets()
    {
        $assets = MetalTraderWallet::where('metal_trader_id', auth()->id())
            ->with([
                'metalItem' => function ($query) {
                    $query->select(['id', 'title', 'unit']);
                }
            ])
            ->get();

        return response()->json($assets);
    }
}
