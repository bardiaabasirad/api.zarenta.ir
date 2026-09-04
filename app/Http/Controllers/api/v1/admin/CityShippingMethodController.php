<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityShippingMethodStoreRequest;
use App\Http\Requests\CityShippingMethodUpdateRequest;
use App\Models\City;
use App\Models\CityShippingMethod;
use App\Models\Province;
use Illuminate\Http\Request;

class CityShippingMethodController extends Controller
{
    public function store(CityShippingMethodStoreRequest $request)
    {
        if ($request->city_id){
            if (CityShippingMethod::where('shipping_method_id', $request->shipping_method_id)->where('city_id', $request->city_id)->exists()) {
                return response()->json([
                    'errors' => [
                        'city_id' => ['این شهر قبلا برای این شیوه ارسال تنظیم شده است']
                    ]
                ], 422);
            }

            CityShippingMethod::create([
                'city_id' => $request->city_id,
                'shipping_method_id' => $request->shipping_method_id,
                'shipping_cost' => $request->shipping_cost,
            ]);

            return response()->json([
                'data' => CityShippingMethod::where('shipping_method_id', $request->shipping_method_id)->with('city.province', 'shippingMethod')->get(),
                'message' => 'شیوه ارسال با موفقیت به شهر انتخاب شده اختصاص داده شد',
            ]);
        }
        else {
            $city_ids = City::where('province_id', $request->province_id)->get()->pluck('id');

            if (CityShippingMethod::where('shipping_method_id', $request->shipping_method_id)->whereIn('city_id', $city_ids)->exists()) {
                return response()->json([
                    'errors' => [
                        'province_id' => ['برخی شهرهای این استان برای این شیوه ارسال قبلا تنظیم شده‌اند']
                    ]
                ], 422);
            }

            foreach ($city_ids as $city_id){
                CityShippingMethod::create([
                    'city_id' => $city_id,
                    'shipping_method_id' => $request->shipping_method_id,
                    'shipping_cost' => $request->shipping_cost,
                ]);
            }

            return response()->json([
                'data' => CityShippingMethod::where('shipping_method_id', $request->shipping_method_id)->with('city.province', 'shippingMethod')->get(),
                'message' => 'شیوه ارسال با موفقیت به شهرهای استان انتخاب شده اختصاص داده شد',
            ]);
        }
    }

    public function update(CityShippingMethodUpdateRequest $request, CityShippingMethod $cityShippingMethod)
    {
        $cityShippingMethod->update($request->validated());

        return response()->json([
            'data' => $cityShippingMethod->load('city','shippingMethod'),
            'message' => 'بروزرسانی با موفقیت انجام شد'
        ]);
    }

    public function show(CityShippingMethod $cityShippingMethod)
    {
        return response()->json([
            'data' => $cityShippingMethod->load('city','shippingMethod'),
        ]);
    }

    public function destroy(CityShippingMethod $cityShippingMethod)
    {
        $cityShippingMethod->delete();

        return response()->json([
            'message' => 'شیوه ارسال با موفقیت از شهر حذف شد',
        ]);
    }
}
