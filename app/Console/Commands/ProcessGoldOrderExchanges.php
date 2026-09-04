<?php

namespace App\Console\Commands;

use App\Constants\AppConstants;
use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProcessGoldOrderExchanges extends Command
{
    protected $signature = 'app:process-gold-order-exchanges';
    protected $description = 'ارسال غیرهم‌زمان سفارش‌های طلا به Node.js بدون تکرار و با ساختار سبک برای اجرای لحظه‌ای توسط PM2';

    protected $maxRetries = 3;

    protected $deliveryMappingByExchange = [
        'darina' => [
            'tomorrow_spot_settlement'              => 'آبشده نقد فردا',
            'day_after_tomorrow_spot_settlement'    => 'آبشده نقد پس فردا',
            'gold_coin_86'                          => 'سکه 86',
            'gold_half_coin_86'                     => 'نیم سکه 86',
            'gold_quarter_coin_86'                  => 'ربع سکه 86',
            'gold_coin_old_version'                 => 'سکه تاریخ پایین',
        ],
        'hajiabdolahi' => [
            'tomorrow_spot_settlement'              => 'آبشده نقد فردا',
            'day_after_tomorrow_spot_settlement'    => 'آبشده نقد پس فردا',
            'gold_coin_86'                          => 'سکه 86',
            'gold_half_coin_86'                     => 'نیم سکه 86',
            'gold_quarter_coin_86'                  => 'ربع سکه 86',
            'gold_coin_old_version'                 => 'سکه تاریخ پایین',
        ]
    ];

    public function handle()
    {
        $this->info("🚀 شروع اجرای Command در حالت PM2 | پردازش یک سفارش pending ...");

        // پیدا کردن اولین سفارش Pending
        $orderExchange = MetalOrderExchange::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$orderExchange) {
            $this->info("⏳ هیچ سفارش جدیدی برای ارسال وجود ندارد.");
            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        // 🔹 آپدیت اتمیک برای جلوگیری از ارسال دوباره
        $updated = MetalOrderExchange::where('id', $orderExchange->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'message' => 'Selected and locked by worker'
            ]);

        if (!$updated) {
            $this->warn("🚫 سفارش {$orderExchange->id} قبلاً توسط worker دیگر گرفته شده است.");
            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        $this->processOrderExchange($orderExchange);
        $this->info("✅ سفارش {$orderExchange->id} پردازش شد و command خروجی گرفت.");

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    protected function processOrderExchange(MetalOrderExchange $orderExchange)
    {
        $metalOrder = MetalOrder::find($orderExchange->gold_order_id);
        if (!$metalOrder) {
            $this->markAsError($orderExchange, 'سفارش یافت نشد');
            return;
        }

        if (!$orderExchange->autoOrderExchange) {
            $this->markAsError($orderExchange, 'صرافی تنظیم نشده است');
            return;
        }

        $exchangeId  = $orderExchange->autoOrderExchange->exchange_id;
        $deliveryMap = $this->deliveryMappingByExchange[$exchangeId] ?? null;
        $product     = $metalOrder->product;

        if (!$deliveryMap || !isset($deliveryMap[$product['name']])) {
            $this->markAsError($orderExchange, "محصول '{$product['name']}' برای صرافی '{$exchangeId}' پشتیبانی نمی‌شود");
            return;
        }

        $typeMap = [
            'tomorrow_spot_settlement'           => AppConstants::TOMORROW,
            'day_after_tomorrow_spot_settlement' => AppConstants::DAY_AFTER_TOMORROW,
        ];
        $type = $typeMap[$metalOrder->product['name']] ?? null;
        if (!$type) {
            $this->markAsError($orderExchange, "نوع محصول '{$metalOrder->product['name']}' ناشناخته است");
            return;
        }

        $this->info("➡️ سفارش {$orderExchange->id} در حال ارسال به صرافی {$exchangeId} ...");

        try {
            $methodName = 'processExchange_' . $exchangeId;
            if (method_exists($this, $methodName)) {
                $this->$methodName($metalOrder, $orderExchange, $deliveryMap[$product['name']]);
            } else {
                $this->markAsError($orderExchange, "صرافی '{$exchangeId}' هنوز پشتیبانی نمی‌شود");
            }
        } catch (\Throwable $e) {
            $this->retryOrFail($orderExchange, $e->getMessage());
        }
    }

    /**
     * 🔹 صرافی Darina
     */
    protected function processExchange_darina($metalOrder, $orderExchange, $delivery)
    {
        $rate = floatval($metalOrder->product['fee']) + floatval($metalOrder->product['fee_margin']);

        try {
            // حالت کاملاً Async: ارسال بدون انتظار پاسخ
            Http::withOptions(['stream' => true])->post('http://localhost:3000/order', [
                'order_id' => $orderExchange->id,
                'rate'     => $rate,
                'action'   => $metalOrder->order_type,
                'delivery' => $delivery,
                'amount'   => $metalOrder->product['quantity'],
            ]);

            $orderExchange->update([
                'status'   => 'processing',
                'message'  => 'Order sent asynchronously to Node.js (Darina).',
                'sent_at'  => now(), // 🔹 زمان ارسال سفارش به صرافی
            ]);

            $this->info("✅ [Darina] سفارش {$orderExchange->id} ارسال شد. منتظر callback از Node.js هستیم...");
        } catch (\Throwable $e) {
            $this->retryOrFail($orderExchange, $e->getMessage());
        }
    }

    /**
     * 🔹 مثال برای صرافی دیگر
     */
    protected function processExchange_hajiabdolahi($metalOrder, $orderExchange, $delivery)
    {
        $rate = floatval($metalOrder->product['fee']) + floatval($metalOrder->product['fee_margin']);
        try {
            Http::withOptions(['stream' => true])->post('http://localhost:3000/order', [
                'order_id' => $orderExchange->id,
                'rate'     => $rate,
                'action'   => $metalOrder->order_type,
                'delivery' => $delivery,
                'amount'   => $metalOrder->product['quantity'],
            ]);

            $orderExchange->update([
                'status'   => 'processing',
                'message'  => 'Order sent asynchronously to Node.js (Another Exchange).',
                'sent_at'  => now(), // 🔹 ذخیره زمان ارسال
            ]);

            $this->info("✅ [Another Exchange] سفارش {$orderExchange->id} ارسال شد به Node.js.");
        } catch (\Throwable $e) {
            $this->retryOrFail($orderExchange, $e->getMessage());
        }
    }

    /**
     * ---------------------
     * 🧩 کمک‌کننده‌ها
     * ---------------------
     */
    protected function markAsError($orderExchange, $message): void
    {
        $orderExchange->update([
            'status' => 'error',
            'message' => $message
        ]);
        $this->error("⚠️ سفارش {$orderExchange->id} → خطا: {$message}");
    }

    protected function retryOrFail($orderExchange, $message): void
    {
        $retryCount = ($orderExchange->retry_count ?? 0) + 1;

        if ($retryCount < $this->maxRetries) {
            $orderExchange->update([
                'retry_count' => $retryCount,
                'status' => 'pending',
                'message' => "Retry {$retryCount}: {$message}"
            ]);
            $this->warn("🔁 سفارش {$orderExchange->id} → تلاش مجدد {$retryCount}");
        } else {
            $orderExchange->update([
                'status' => 'error',
                'message' => "حداکثر تلاش‌ها انجام شد: {$message}"
            ]);
            $this->error("❌ سفارش {$orderExchange->id} → شکست پس از {$retryCount} تلاش");
        }
    }
}
