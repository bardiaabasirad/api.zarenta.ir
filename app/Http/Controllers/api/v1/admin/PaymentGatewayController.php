<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGatewayRequest;
use App\Models\PaymentGateway;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        return response()->json([
            'gateways' => PaymentGateway::all(),
        ]);
    }

    public function update(UpdateGatewayRequest $request, PaymentGateway $gateway)
    {
        $gateway->update($request->validated());

        return response()->json([
            'message' => 'تنظیمات با موفقیت بروزرسانی شد'
        ]);
    }
}
