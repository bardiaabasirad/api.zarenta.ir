<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SelectedMetalPriceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MetalPriceController extends Controller
{
    public function newPrice(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items)) {
            return response()->noContent();
        }

        $priceSourceId = $request->input('price_source_id', 1);
        $today = Carbon::now()->format('Y-m-d');

        foreach ($items as $item) {

            // اگر buy و sell هر دو خالی باشند ادامه نده
            if (empty($item['buy']) && empty($item['sell'])) {
                continue;
            }

            $rawTime = $item['time'] ?? null;
            $time = null;

            if ($rawTime) {
                // فقط ساعت
                if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $rawTime)) {
                    $time = Carbon::parse($today . ' ' . $rawTime);
                }
                // تاریخ + ساعت
                else if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rawTime)) {
                    $time = Carbon::parse($rawTime);
                }
                // فقط تاریخ
                else if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTime)) {
                    $time = Carbon::parse($rawTime . ' ' . Carbon::now()->format('H:i:s'));
                }
            }

            // ساخت آیتم نهایی برای ارسال به سرویس
            $parsedItem = [
                'metal_item_id'   => $item['metal_item_id'] ?? null,
                'price_source_id' => $priceSourceId,
                'buy'             => $item['buy'] ?? null,
                'sell'            => $item['sell'] ?? null,
                'time'            => $time,
            ];

            SelectedMetalPriceService::handleNewPrice($parsedItem);
        }

        // پاکسازی cache های موقت
        SelectedMetalPriceService::resetRequestCache();

        return response()->noContent();
    }
}
