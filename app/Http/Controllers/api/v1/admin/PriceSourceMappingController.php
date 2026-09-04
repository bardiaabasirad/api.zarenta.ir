<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\PriceSourceMapping;
use Illuminate\Http\Request;

class PriceSourceMappingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'buy'                   => 'sometimes|nullable|string',
            'sell'                  => 'sometimes|nullable|string',
            'buy_from_sell'         => 'sometimes|nullable|string',
            'sell_from_buy'         => 'sometimes|nullable|string',
            'generate_buy_or_sell'  => 'sometimes|boolean',
            'metal_item_id'         => 'required|exists:metal_items,id',
            'price_source_id'       => 'sometimes|exists:price_sources,id',
        ]);

        $priceSourceMapping = PriceSourceMapping::updateOrCreate(
            ['metal_item_id' => $validated['metal_item_id']],
            $validated
        );

        \Cache::delete("price_source_mapping_{$validated['metal_item_id']}");

        return response()->json([
            'price_source_mapping' => $priceSourceMapping,
        ], 200);
    }
}
