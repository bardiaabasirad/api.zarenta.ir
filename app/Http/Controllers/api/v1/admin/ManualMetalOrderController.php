<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualMetalOrderRequest;
use App\Models\MetalItem;
use App\Services\SelectedMetalPriceService;

class ManualMetalOrderController extends Controller
{
    public function create()
    {
        $metalItems = MetalItem::withoutGlobalScope('visible')->get(['id', 'title']);
        $latestRawPriceFromSelectedSource = SelectedMetalPriceService::getLatestRawPriceFromSelectedSource();

        return response()->json([
           'metalItems' => $metalItems,
           'latestRawPriceFromSelectedSource' => $latestRawPriceFromSelectedSource,
        ]);
    }

    public function store(ManualMetalOrderRequest $request)
    {

    }
}
