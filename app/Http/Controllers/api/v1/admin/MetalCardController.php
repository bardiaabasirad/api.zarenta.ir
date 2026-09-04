<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalCard;
use App\Models\PriceSource;
use App\Models\Setting;
use Illuminate\Http\Request;

class MetalCardController extends Controller
{
    public function index()
    {
        $refChannelSetting = Setting::where('option_key', 'reference_channel_id')->first();

        $referenceChannel = PriceSource::find($refChannelSetting->option_value);
        $priceSources = PriceSource::all();

        return response()->json([
            'reference' => $referenceChannel,
            'price_sources' => $priceSources,
            'melted_gold_cards' => MetalCard::orderBy('order')->get(),
        ]);
    }

    public function update(Request $request, MetalCard $card)
    {
        $validatedData = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'buy_fixed'         => 'sometimes',
            'buy_percentage'    => 'sometimes',
            'sell_fixed'        => 'sometimes',
            'sell_percentage'   => 'sometimes',
        ]);

        $card->update($validatedData);

        return response()->json([
            'card' => $card,
            'message' => 'تنظیمات با موفقیت بروزرسانی شد'
        ]);
    }
}
