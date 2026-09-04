<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\DealingGroup;
use App\Models\MetalItem;
use App\Models\Setting;

class MetalSettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'default_metal_trader_group' => Setting::where('option_key', 'default_metal_trader_group_id')->first(),
            'molten_page_metal_item' => Setting::where('option_key', 'molten_page_metal_item_id')->first(),
            'jewelry_sale_hedge_metal_item' => Setting::where('option_key', 'jewelry_sale_hedge_metal_item_id')->first(),
            'dealing_groups' => DealingGroup::select('id', 'name')->get(),
            'metal_items' => MetalItem::select('id', 'title')->get(),
        ]);
    }
}
