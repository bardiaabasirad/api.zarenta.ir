<?php

namespace App\Services;

use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Models\SelectedAutoOrderExchange;
use Illuminate\Support\Facades\Http;

class PuppeteerService
{
    /**
     * نگاشت محصولات به نوع و متن تحویل
     */
    private static $productMap = [
        'tomorrow_spot_settlement' => ['type' => 'tomorrow', 'delivery' => 'آبشده نقد فردا'],
        'day_after_tomorrow_spot_settlement' => ['type' => 'day_after_tomorrow', 'delivery' => 'آبشده نقد پس فردا'],
        // اگر لازم بود محصولات جدید اضافه کن اینجا
    ];

    /**
     * ثبت سفارش طلا با استفاده از Puppeteer
     */
    public static function placeOrder(MetalOrder $goldOrder)
    {
        // بررسی اینکه محصول پشتیبانی می‌شود یا نه
        if (!isset(self::$productMap[$goldOrder->product['name']])) {
            return; // محصول در نقشه وجود ندارد، کاری انجام نمی‌دهیم
        }

        $info = self::$productMap[$goldOrder->product['name']];
        $activeBroker = self::targetExchange($info['type'], $goldOrder->product);

        if ($activeBroker && $activeBroker->autoOrderExchange->exchange_id === 'darina') {
            self::placeDarinaOrder($goldOrder, $info['delivery'], $activeBroker->auto_order_exchange_id);
        }
    }

    /**
     * یافتن بروکر فعال بر اساس نوع تحویل
     */
    private static function targetExchange($type, $product)
    {
        return SelectedAutoOrderExchange::where('status', 'active')
            ->where('type', $type)
            ->where('min', '<=', $product['weight'])
            ->where('max', '>=', $product['weight'])
            ->with('autoOrderExchange')
            ->first();
    }

    /**
     * ثبت سفارش در سرور Puppeteer برای صرافی Darina
     */
    private static function placeDarinaOrder(MetalOrder $goldOrder, $delivery, $autoOrderExchange_id)
    {
        try {
            // ارسال درخواست به Puppeteer
            $response = Http::timeout(10)
                ->post('http://localhost:3000/order', [
                    'action' => $goldOrder->order_type,
                    'delivery' => $delivery,
                    'amount' => $goldOrder->product['weight']
                ]);

            // اگر پاسخ HTTP غیر موفق بود (غیر 2xx)
            if (!$response->ok()) {
                MetalOrderExchange::create([
                    'gold_order_id' => $goldOrder->id,
                    'auto_order_exchange_id' => $autoOrderExchange_id,
                    'status' => 'failed',
                    'message' => 'HTTP error: ' . $response->status(),
                ]);
                return;
            }

            // خواندن فیلدهای پاسخ Puppeteer
            $success = $response->json('success');
            $message = $response->json('message');

            // ذخیره وضعیت سفارش در دیتابیس
            MetalOrderExchange::create([
                'gold_order_id' => $goldOrder->id,
                'auto_order_exchange_id' => $autoOrderExchange_id,
                'status' => $success === true ? 'approved' : 'rejected',
                'message' => $message,
            ]);

        } catch (\Throwable $e) {
            // ذخیره خطاهای شبکه یا داخلی
            MetalOrderExchange::create([
                'gold_order_id' => $goldOrder->id,
                'auto_order_exchange_id' => $autoOrderExchange_id,
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
