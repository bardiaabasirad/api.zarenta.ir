<?php

namespace App\Services\Hamtala;

use App\Constants\AppConstants;
use App\Enums\StockMode;
use App\Exceptions\HamtalaApiException;
use App\Jobs\SubmitHamtalaOrder;
use App\Models\MetalItem;
use App\Models\MetalItemAutoOrderSetting;
use App\Models\MetalItemPriceSource;
use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Models\Setting;
use App\Services\KimiaService;
use App\Services\MetalOrderService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class HamtalaOrderExchangeService
{
    private const GROUP_MELTED = 1;
    private const HAMTALA_SOURCE_ID = 5;

    /**
     * Cache برای تنظیمات auto-order در scope درخواست جاری
     */
    private array $autoOrderSettingsCache = [];

    public const SKIP_REASONS = [
        'minimum_weight_not_met',
        'maximum_weight_not_met',
        'should_confirm',
    ];

    public function __construct(
        private readonly HamtalaApiService   $apiService,
        private readonly HamtalaPriceService $hamtalaPriceService,
    )
    {
    }

    /**
     * دریافت تنظیمات auto-order با cache
     */
    private function getAutoOrderSetting(int $metalItemId): ?MetalItemAutoOrderSetting
    {
        if (!isset($this->autoOrderSettingsCache[$metalItemId])) {
            $this->autoOrderSettingsCache[$metalItemId] = MetalItemAutoOrderSetting::where([
                'metal_item_id' => $metalItemId,
                'price_source_id' => self::HAMTALA_SOURCE_ID,
            ])->first();
        }

        return $this->autoOrderSettingsCache[$metalItemId];
    }

    public function isSkipReason(string $reason): bool
    {
        return in_array($reason, self::SKIP_REASONS, true);
    }

    public function submitAutoOrder(MetalOrder $metalOrder): array
    {
        $prepared = $this->prepareAutoOrderData($metalOrder);

        if (!is_array($prepared)) {
            if ($this->isSkipReason($prepared)) {
                return [
                    'success' => $prepared == 'should_confirm',
                    'status' => 'skipped',
                    'message' => null,
                    'reason' => $prepared,
                ];
            }

            return [
                'success' => false,
                'status' => 'validation_failed',
                'message' => 'اعتبارسنجی سفارش ناموفق بود.',
                'reason' => $prepared,
            ];
        }

        SubmitHamtalaOrder::dispatch($metalOrder);

        return [
            'success' => true,
            'status' => 'queued',
            'message' => 'سفارش در صف ثبت قرار گرفت.',
            'reason' => null,
        ];
    }

    public function prepareAutoOrderData(MetalOrder $metalOrder): array|string
    {
        $metalItemID = $metalOrder->product['metal_item_id'];

        $externalIdentifier = MetalItemPriceSource::where([
            'metal_item_id' => $metalItemID,
            'price_source_id' => self::HAMTALA_SOURCE_ID,
        ])->value('order_external_identifier');

        if (!$externalIdentifier) {
            return 'mapping_not_found';
        }

        $metalItemAutoOrder = $this->getAutoOrderSetting($metalItemID);

        if (!$metalItemAutoOrder) {
            return 'auto_order_setting_not_found';
        }

        if (!$metalItemAutoOrder->is_enabled) {
            return 'auto_order_dispatch_is_off';
        }

        $minWeight = $metalItemAutoOrder->min_auto_buy_weight ?? 0;

        if ($metalOrder->product['quantity'] < $minWeight) {
            return "minimum_weight_not_met";
        }

        $maxWeight = $metalItemAutoOrder->max_auto_buy_weight ?? INF;

        if ($metalOrder->product['quantity'] > $maxWeight) {
            return "maximum_weight_not_met";
        }

        $orderType = 1; // نوع سفارش عادی

        $weight = null;
        $count = null;

        $isBuy = $metalOrder->order_type === 'buy';

        $buyOrSell = $isBuy ? 1 : 2;

        $quantity = round((float)($metalOrder->product['quantity'] ?? 0), 3);

        if (($metalOrder->product['en_unit'] ?? null) === 'count') {
            $count = $quantity;
            $productType = '2'; // سکه
        } else {
            $productType = '1'; // طلا

            $metalItem = MetalItem::query()
                ->select('id', 'metal_item_group_id')
                ->findOrFail($metalItemID);

            $settings = Setting::whereIn('option_key', [
                'min_melted_stock_quantity',
                'max_melted_stock_quantity',
                'melted_stock_quantity',
            ])->pluck('option_value', 'option_key');

            if ($metalItem->metal_item_group_id === self::GROUP_MELTED) {
                if ($isBuy) {
                    $newStock = round((float)$settings['melted_stock_quantity'] - $quantity, 3);
                    if ($newStock >= $settings['min_melted_stock_quantity']) {
                        return 'should_confirm';
                    }
                    $weight = min($settings['min_melted_stock_quantity'] - $newStock, $quantity);
                } else {
                    $newStock = round((float)$settings['melted_stock_quantity'] + $quantity, 3);
                    if ($newStock <= $settings['max_melted_stock_quantity']) {
                        return 'should_confirm';
                    }
                    $weight = min($newStock - $settings['max_melted_stock_quantity'], $quantity);
                }
            } else {
                $weight = $quantity;
            }
        }

        $data = $metalOrder->extra_data ?? [];
        $data['auto_order_quantity'] = $weight;
        $metalOrder->extra_data = $data;
        $metalOrder->save();

        return [
            'product_id' => $externalIdentifier,
            'external_identifier' => $externalIdentifier,
            'buy_or_sell' => $buyOrSell,
            'order_type' => $orderType,
            'weight' => $weight,
            'count' => $count,
            'description' => "معامله {$metalOrder->product['name']}",
            'product_type' => $productType,
            'product_name' => $metalOrder->product['name'],
        ];
    }

    public function isWeightOutOfRange(MetalOrder $metalOrder): bool
    {
        $metalItemID = $metalOrder->product['metal_item_id'];
        $metalItemAutoOrder = $this->getAutoOrderSetting($metalItemID);

        if (!$metalItemAutoOrder) {
            return false;
        }

        $quantity = $metalOrder->product['quantity'];
        $minWeight = $metalItemAutoOrder->min_auto_buy_weight ?? 0;
        $maxWeight = $metalItemAutoOrder->max_auto_buy_weight ?? INF;

        return $quantity < $minWeight || $quantity > $maxWeight;
    }

    public function isAutoOrderDisabled(MetalOrder $metalOrder): bool
    {
        $metalItemID = $metalOrder->product['metal_item_id'];
        $metalItemAutoOrder = $this->getAutoOrderSetting($metalItemID);

        if (!$metalItemAutoOrder) {
            return true;
        }

        return !$metalItemAutoOrder->is_enabled;
    }

    public function createPendingExchange($metal_order_id): MetalOrderExchange
    {
        return MetalOrderExchange::create([
            'metal_order_id' => $metal_order_id,
            'price_source_id' => self::HAMTALA_SOURCE_ID,
            'status' => 'pending',
            'details' => [],
        ]);
    }

    public function buildPayload(
        MetalOrder         $metalOrder,
        MetalOrderExchange $metalOrderExchange,
        array              $preparedData
    ): array
    {
        $productPrice = $this->hamtalaPriceService->getProductPrice($metalOrder->product['metal_item_id']);

        if (($preparedData['buy_or_sell'] ?? null) === 1) {
            $mazane = $productPrice['price_buy'];
        } else {
            $mazane = $productPrice['price_sell'];
        }

        if (($metalOrder->product['en_unit'] ?? null) === 'count') {
            $price = $preparedData['count'] * $mazane;
        } else {
            $price = round(
                $preparedData['weight']
                * $mazane
                / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR
            );
        }

        return array_merge($preparedData, [
            'order_id' => $metalOrderExchange->id,
            'mazane' => $mazane,
            'price' => $price,
            'our_mazane' => $productPrice,
        ]);
    }

    public function markExchangeAsProcessing(
        MetalOrderExchange $metalOrderExchange,
        array              $payload
    ): void
    {
        $details = $metalOrderExchange->details ?? [];
        $details['request'] = $payload;

        if (!$metalOrderExchange->fresh()->isFinalized()) {
            $metalOrderExchange->update([
                'status' => 'processing',
                'rate' => $payload['price'] ?? null,
                'details' => $details,
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * @throws HamtalaApiException
     * @throws ConnectionException
     */
    public function sendPayload(array $payload): array
    {
        return $this->apiService->receiveOrder($payload);
    }


    /**
     * @throws Throwable
     */
    public function syncResponse(MetalOrderExchange $metalOrderExchange, array $response, $metalOrder): void
    {
        $remoteStatus = (int)($response['status'] ?? HamtalaConstants::ORDER_FAILED);
        $message = $response['message'] ?? null;

        $details = $metalOrderExchange->details ?? [];
        $details['response'] = $response;

        if (isset($response['order_id'])) {
            $details['external_order_id'] = $response['order_id'];
        }

        $update = [
            'message' => $message,
            'details' => $details,
        ];

        if ($remoteStatus === HamtalaConstants::CONFIRM_SUCCESS) {
            $update['status'] = 'confirmed';
            $update['placed_at'] = now();

            if (!$metalOrder->fresh()->isFinalized()) {
                try {
                    MetalOrderService::markAsSucceedAndUpdateStock(
                        $metalOrder,
                        ['succeed_at' => Carbon::now()],
                        StockMode::Rebalance
                    );
                } catch (Exception $e) {
                    Log::channel('checkStaleMetalOrders')
                        ->error("Failed to process skipped order transaction: " . $e->getMessage(), [
                            'metal_order_id' => $metalOrder->id,
                            'exception' => $e,
                        ]);
                    throw $e;
                }

                KimiaService::submitHamtalaOrder($metalOrder, $metalOrderExchange);
            }
        } elseif ($remoteStatus === HamtalaConstants::ORDER_PENDING) {
            $update['status'] = 'placed';
            $update['placed_at'] = now();
        } elseif ($remoteStatus === HamtalaConstants::ORDER_NO_PRODUCT) {
            $update['status'] = 'rejected';
            $update['resolved_at'] = now();

            if (!$metalOrder->fresh()->isFinalized()) {
                MetalOrderService::markAsRejected(
                    $metalOrder,
                    [
                        'cause' => 'partner_retry_limit_reached',
                        'message' => $message,
                        'rejected_at' => Carbon::now(),
                    ]
                );
            }
        } else {
            $update['status'] = 'error';
            $update['resolved_at'] = now();

            if (!$metalOrder->fresh()->isFinalized()) {
                MetalOrderService::markAsRejected(
                    $metalOrder,
                    [
                        'cause' => 'partner_retry_limit_reached',
                        'message' => $message,
                        'rejected_at' => Carbon::now(),
                    ]
                );
            }
        }

        if (!$metalOrderExchange->fresh()->isFinalized()) {
            $metalOrderExchange->update($update);
        }
    }

    public function markExchangeAsRetryFailed(
        MetalOrderExchange $metalOrderExchange,
        Throwable          $exception,
        int                $attempts
    ): void
    {
        if (!$metalOrderExchange->fresh()->isFinalized()) {
            $details = $metalOrderExchange->details ?? [];
            $details['last_exception'] = $exception->getMessage();

            $metalOrderExchange->update([
                'status' => 'error',
                'retry_count' => $attempts,
                'details' => $details,
                'resolved_at' => now(),
            ]);
        }
    }
}
