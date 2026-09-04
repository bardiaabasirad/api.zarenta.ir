<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\MarketPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketPriceController extends Controller
{
    public function last()
    {
        $marketPrice = MarketPrice::latest('read_at')->first();

        if (! $marketPrice) {
            return response()->json(['message' => 'No market price found.'], 404);
        }

        $currentReadAt = Carbon::parse($marketPrice->read_at);
        $previousDayEnd = $currentReadAt->copy()->subDay()->endOfDay();
        $secondsUntilEndOfDay = now()->diffInSeconds(now()->copy()->endOfDay());

        $previousDayMarketPrice = Cache::remember(
            'market_price.previous_day.' . $currentReadAt->toDateString(),
            $secondsUntilEndOfDay,
            fn () => MarketPrice::where('read_at', '<=', $previousDayEnd)
                ->latest('read_at')
                ->first()
        );

        $previousMarketPrice = Cache::remember(
            'market_price.previous.' . $marketPrice->id,
            60,
            fn () => MarketPrice::where('read_at', '<', $marketPrice->read_at)
                ->latest('read_at')
                ->first()
        );

        $response = $marketPrice->toArray();

        if ($previousDayMarketPrice) {
            $response = array_merge($response, [
                'prev_day_price' => $previousDayMarketPrice->price,
                'prev_day_dollar' => $previousDayMarketPrice->dollar,
                'prev_day_ounce' => $previousDayMarketPrice->ounce,
                'prev_day_emam_coin' => $previousDayMarketPrice->emam_coin,
                'prev_day_full_coin' => $previousDayMarketPrice->full_coin,
                'prev_day_half_coin' => $previousDayMarketPrice->half_coin,
                'prev_day_quarter_coin' => $previousDayMarketPrice->quarter_coin,
            ]);
        }

        if ($previousMarketPrice) {
            $response['prev_price'] = $previousMarketPrice->price;
        }

        return response()->json($response);
    }

}
