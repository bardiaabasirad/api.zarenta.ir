<?php

namespace App\Console\Commands;

use App\Enums\StockMode;
use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Models\Setting;
use App\Services\Hamtala\HamtalaApiService;
use App\Services\KimiaService;
use App\Services\MetalOrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class InquireOrdersStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hamtala:inquire-orders-status
                            {--limit=50 : Maximum number of orders to check}
                            {--status=placed : Filter orders by status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inquire placed orders status from Hamtala API';

    private HamtalaApiService $hamtalaApiService;

    public function __construct(HamtalaApiService $hamtalaApiService)
    {
        parent::__construct();
        $this->hamtalaApiService = $hamtalaApiService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $statusFilter = $this->option('status');

        try {
            // دریافت سفارشات placed که نیاز به بررسی دارند
            $orders = MetalOrderExchange::where('status', $statusFilter)
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            if ($orders->isEmpty()) {
                return self::SUCCESS;
            }

            $sourceOrderIds = $orders->pluck('id')->toArray();

            // استعلام دسته‌ای (حداکثر ۵۰ تا)
            $chunks = array_chunk($sourceOrderIds, 50);

            foreach ($chunks as $index => $chunk) {
                try {
                    $response = $this->hamtalaApiService->inquireOrderStatus(
                        sourceOrderIds: $chunk
                    );

                    // پردازش پاسخ و به‌روزرسانی وضعیت سفارشات
                    if (isset($response['status']) && $response['status'] == 1) {
                        // پردازش سفارشات موفق
                        if (isset($response['orders']) && is_array($response['orders'])) {
                            foreach ($response['orders'] as $orderStatus) {
                                try {
                                    $this->updateOrderStatus($orderStatus);
                                } catch (Throwable $e) {
                                    Log::channel('checkStaleMetalOrders')->error("Failed to update metal order status", [
                                        'order_data' => $orderStatus,
                                        'error_message' => $e->getMessage(),
                                        'file' => $e->getFile(),
                                        'line' => $e->getLine(),
                                        'trace' => $e->getTraceAsString()
                                    ]);

                                    // نمایش خطا در کنسول (چون از $this->info استفاده کردی، احتمالا در Command هستی)
                                    $this->error("Failed to process an order. Check logs for details.");
                                }
                            }
                            $this->info("Processed " . count($response['orders']) . " order(s) successfully.");
                        }
                    } else {
                        $this->error("API returned unsuccessful status.");
                        Log::channel('checkStaleMetalOrders')->error('Hamtala API inquiry failed', ['response' => $response]);
                    }

                    $this->info("Chunk " . ($index + 1) . " processed successfully.");

                } catch (\Exception $e) {
                    $this->error("Error processing chunk " . ($index + 1) . ": " . $e->getMessage());
                    Log::channel('checkStaleMetalOrders')->error('Hamtala order status inquiry failed', [
                        'chunk' => $chunk,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info('Order status inquiry completed.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to inquire orders status: ' . $e->getMessage());
            Log::channel('checkStaleMetalOrders')->error('Hamtala order status inquiry command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Update order status based on API response
     *
     * @param array $orderStatus
     * @return void
     * @throws Throwable
     */
    private function updateOrderStatus(array $orderStatus): void
    {
        try {
            $metalOrderExchange = MetalOrderExchange::where('id', $orderStatus['source_order_id'] ?? null)
                ->first();

            if (!$metalOrderExchange) {
                $this->warn("Order not found: source_order_id={$orderStatus['source_order_id']}");
                return;
            }

            $statusCode = $orderStatus['status_code'] ?? null;
            $orderStatusValue = $orderStatus['order_status'] ?? null;

            // نگاشت وضعیت‌های API به وضعیت‌های داخلی
            $newStatus = $this->mapApiStatusToInternal($statusCode);

            if ($metalOrderExchange->status !== $newStatus) {
                $metalOrderExchange->status = $newStatus;
                $metalOrderExchange->resolved_at = now();

                Log::channel('checkStaleMetalOrders')->info('Order status changed to ' . $newStatus);

                $metalOrder = MetalOrder::findOrFail($metalOrderExchange->metal_order_id);

                if ($newStatus == 'confirmed') {
                    Log::channel('checkStaleMetalOrders')->info("MetalOrderExchange {$metalOrderExchange->id} confirmed");

                    MetalOrderService::markAsSucceedAndUpdateStock(
                        $metalOrder,
                        ['succeed_at' => Carbon::now()],
                        StockMode::Rebalance
                    );

                    $details = $metalOrderExchange->details ?? [];

                    $metalOrderExchange->details = array_merge(
                        $details,
                        [
                            'hamtala_status_code' => $statusCode,
                            'hamtala_order_status' => $orderStatusValue,
                        ]
                    );
                    $metalOrderExchange->status = 'confirmed';
                    $metalOrderExchange->resolved_at = now();
                    $metalOrderExchange->save();

                    KimiaService::submitHamtalaOrder($metalOrder, $metalOrderExchange);

                    Log::channel('checkStaleMetalOrders')->info("MetalOrder {$metalOrderExchange->metal_order_id} succeed");
                } else {
                    $validityPeriod = (int)Setting::where('option_key', 'validity_period_of_melted_order_before_expires')
                        ->value('option_value');

                    if (now()->greaterThan($metalOrder->created_at->addSeconds($validityPeriod))) {
                        MetalOrderService::markAsRejectedAndRevertStock(
                            $metalOrder,
                            [
                                'cause' => 'rejected',
                                'message' => 'تغییر مظنه',
                                'rejected_at' => Carbon::now(),
                            ]
                        );

                        $details = $metalOrderExchange->details ?? [];

                        $metalOrderExchange->details = array_merge(
                            $details,
                            [
                                'hamtala_status_code' => $statusCode,
                                'hamtala_order_status' => $orderStatusValue,
                            ]
                        );
                        $metalOrderExchange->status = 'rejected';
                        $metalOrderExchange->resolved_at = now();
                        $metalOrderExchange->save();
                    }
                }

                $this->info("Order {$metalOrderExchange->id} status updated: {$newStatus}");
            }

        } catch (\Exception $e) {
            $this->error("Failed to update order status: " . $e->getMessage());
            Log::channel('checkStaleMetalOrders')->error('Failed to update order status', [
                'order_status' => $orderStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map API status to internal status
     *
     * @param int|null $statusCode
     * @return string
     */
    private function mapApiStatusToInternal(?int $statusCode): string
    {
        // نگاشت بر اساس status_code
        return match ($statusCode) {
            0, -3, -4 => 'processing',
            1 => 'confirmed',
            -1, -2 => 'rejected',
            default => 'error',
        };
    }
}
