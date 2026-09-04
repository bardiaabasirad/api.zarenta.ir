<?php

namespace App\Http\Controllers\api\v1\external;

use App\Http\Controllers\Controller;
use App\Http\Resources\TabanMarketResource;
use App\Http\Resources\TelMarketResource;
use App\Models\MarketPrice;
use App\Models\Setting;
use App\Models\RawMetalPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketController extends Controller
{
    public function market()
    {
        $setting = Setting::where('option_key', 'reference_channel_id')->first();
        $telMarket = RawMetalPrice::latest()->where('reference_channel_id', $setting->option_value)->first();

        if(Carbon::parse($telMarket->time)->isToday()){
            $yesterday_rate = RawMetalPrice::where('reference_channel_id', $setting->option_value)
                ->whereDate('time', Carbon::yesterday())
                ->latest()
                ->first();

            if ($yesterday_rate){
                $telMarket->prev_buy = $yesterday_rate->buy;
                $telMarket->prev_sell = $yesterday_rate->sell;
            }
        }

        $market = Cache::remember('market_price.latest', 60, function () {
            return MarketPrice::latest()->first();
        });
        $previousDay = Carbon::parse($market->read_at)->subDay()->setTime(23, 59);

        $previousDayMarketPrice = Cache::remember('market_price.previous_day', 60, function () use ($previousDay) {
            return MarketPrice::whereDate('read_at', '<=', $previousDay)->latest()->first();
        });

        $previousMarketPrice = Cache::remember('market_price.previous', 60, function () use ($market) {
            return MarketPrice::where('id', '<', $market->id)->latest()->first();
        });

        $market->prev_day_price = $previousDayMarketPrice ? $previousDayMarketPrice->price : null;
        $market->prev_day_dollar = $previousDayMarketPrice ? $previousDayMarketPrice->dollar : null;
        $market->prev_day_ounce = $previousDayMarketPrice ? $previousDayMarketPrice->ounce : null;
        $market->prev_day_emam_coin = $previousDayMarketPrice ? $previousDayMarketPrice->emam_coin : null;
        $market->prev_day_full_coin = $previousDayMarketPrice ? $previousDayMarketPrice->full_coin : null;
        $market->prev_day_half_coin = $previousDayMarketPrice ? $previousDayMarketPrice->half_coin : null;
        $market->prev_day_quarter_coin = $previousDayMarketPrice ? $previousDayMarketPrice->quarter_coin : null;
        $market->prev_price = $previousMarketPrice ? $previousMarketPrice->price : null;

        return response()->json([
            'market' => new TabanMarketResource($market),
            'tel_market' => new TelMarketResource($telMarket),
        ]);
    }

    public function marketComparison()
    {
        $id = request()->query('id');
        $type = request()->query('type');

        if ($type == 'taban'){
            $freshMarket = Cache::remember('market_price.latest', 60, function () {
                return MarketPrice::latest()->first();
            });

            $originalMarket = MarketPrice::find($id);

            return response()->json([
                'fresh_market' => new TabanMarketResource($freshMarket),
                'original_market' => new TabanMarketResource($originalMarket),
            ]);
        }
        else if($type == 'telegram') {
            $setting = Setting::where('option_key', 'reference_channel_id')->first();
            $freshMarket = RawMetalPrice::latest()->where('reference_channel_id', $setting->option_value)->first();
            $originalMarket = RawMetalPrice::find($id);

            return response()->json([
                'fresh_market' => new TelMarketResource($freshMarket),
                'original_market' => new TelMarketResource($originalMarket),
            ]);
        }
    }
}
