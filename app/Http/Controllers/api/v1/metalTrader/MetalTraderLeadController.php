<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalTraderLeadRequest;
use App\Services\MetalTraderLeadService;

class MetalTraderLeadController extends Controller
{
    public function store(MetalTraderLeadRequest $request)
    {
        $phone = $request->input('phone');

        app(MetalTraderLeadService::class)->track($phone, 'consultation_request');

        return response()->json([
            'message' => 'درخواست مشاوره شما با موفقیت ثبت شد.',
        ], 200);
    }
}
