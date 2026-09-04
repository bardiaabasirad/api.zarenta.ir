<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShippingMethodStoreRequest;
use App\Http\Requests\ShippingMethodUpdateRequest;
use App\Models\City;
use App\Models\CityShippingMethod;
use App\Models\Province;
use App\Models\ShippingMethod;

class ShippingMethodController extends Controller
{
    public function index()
    {
        return response()->json([
           'data' => ShippingMethod::all()
        ]);
    }

    public function show(ShippingMethod $shippingMethod)
    {
        return response()->json([
            'provinces' => Province::all(),
            'cities' => City::all(),
            'data' => $shippingMethod,
            'cityShippingMethod' => CityShippingMethod::where('shipping_method_id', $shippingMethod->id)->with('city.province','shippingMethod')->get(),
        ]);
    }

    public function store(ShippingMethodStoreRequest $request)
    {
        $shippingMethod = ShippingMethod::create($request->validated());

        return response()->json([
           'shipping_method' => $shippingMethod,
           'message' => 'شیوه ارسال با موفقیت ثبت شد',
        ]);
    }

    public function update(ShippingMethodUpdateRequest $request, ShippingMethod $shippingMethod)
    {
        $shippingMethod->update($request->validated());

        return response()->json([
           'data' => $shippingMethod,
           'message' => 'شیوه ارسال با موفقیت بروزرسانی شد',
        ]);
    }
}
