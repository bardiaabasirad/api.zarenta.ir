<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\PriceSourceMapping;
use App\Models\RawMetalPrice;
use App\Models\SelectedMetalPrice;
use App\Services\SelectedMetalPriceService;
use Illuminate\Http\Request;

class SelectedMetalPriceController extends Controller
{
    public function useRate(Request $request)
    {
        $rawMetalPrice = RawMetalPrice::find($request->raw_metal_price_id);

        $priceSourceMapping = PriceSourceMapping::where('metal_item_id', $rawMetalPrice->metal_item_id)->first();

        $variables = ['buy' => $rawMetalPrice->buy, 'sell' => $rawMetalPrice->sell];

        SelectedMetalPriceService::createRate(
            $rawMetalPrice,
            $priceSourceMapping,
            $variables,
            true
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sell' => 'required|integer',
            'metal_item_id' => 'required|exists:metal_items,id',
        ]);

        $metalItem = MetalItem::select('buy_sell_spread')
            ->find($validated['metal_item_id']);

        $sell = (int) $validated['sell'];
        $buy = $sell - (int) $metalItem->buy_sell_spread;

        $SelectedMetalPrice = SelectedMetalPrice::create([
            'buy'           => $buy,
            'sell'          => $sell,
            'metal_item_id' => $validated['metal_item_id'],
            'time'          => now(),
        ]);

        return response()->json([
            'selected_metal_price' => $SelectedMetalPrice,
        ]);
    }
}
