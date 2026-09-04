<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateKimiBalanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function updateAggregatedViewOfInvoices(Request $request)
    {
        $metalTrader = auth('metal-trader-api')->user();

        $metalTrader->aggregated_view_of_invoices = $request->value;
        $metalTrader->save();

        Cache::forget("aggregated_view_of_invoices_{$metalTrader->id}");

        UpdateKimiBalanceJob::dispatch($metalTrader);

        return response()->json([
            'status' => $metalTrader->aggregated_view_of_invoices
        ]);
    }

}
