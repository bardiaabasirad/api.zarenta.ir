<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function updateMarketOpeningNotification(Request $request)
    {
        $metalTrader = auth('metal-trader-api')->user();

        $metalTrader->market_opening_notification = $request->value;
        $metalTrader->save();

        return response()->json([
            'status' => $metalTrader->market_opening_notification
        ]);
    }
}
