<?php

namespace App\Http\Controllers;

use App\Exceptions\HamtalaApiException;
use App\Services\Hamtala\HamtalaApiService;
use App\Services\Hamtala\HamtalaPriceService;
use Illuminate\Http\Request;

class HamtalaController extends Controller
{
    public function getLatestPrices(HamtalaPriceService $service)
    {
        $data = $service->getLatestPrices();

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'قیمت‌ها هنوز دریافت نشده‌اند'], 503);
    }

    /**
     * @throws HamtalaApiException
     */
    public function getOrderStatus(Request $request, HamtalaApiService $hamtalaApiService)
    {
        $orderIds = $request->input('orderIds', []);

        return $hamtalaApiService->inquireOrderStatus(
            sourceOrderIds: $orderIds
        );
    }

    public function getProductPrice(int $product_id, HamtalaPriceService $service)
    {
        $price = $service->getProductPrice($product_id);

        return $price
            ? response()->json($price)
            : response()->json(['message' => 'محصول یافت نشد'], 404);
    }

    public function listProductCached(HamtalaApiService $hamtala)
    {
        $products = $hamtala->warmUpProducts();

        return response()->json($products);
    }

    public function listProduct(HamtalaApiService $hamtala)
    {
        $products = $hamtala->listProduct();

        return response()->json($products);
    }
}
