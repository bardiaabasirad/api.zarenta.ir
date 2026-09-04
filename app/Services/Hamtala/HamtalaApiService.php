<?php

namespace App\Services\Hamtala;

use App\Exceptions\HamtalaApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

class HamtalaApiService
{
    public function __construct(
        private readonly HamtalaClient $client
    ) {}

    public function listProduct(): array
    {
        return $this->client->post('/api/v1/appLink/listProduct');
    }

    public function warmUpProducts(int $ttl = 300): array
    {
        return Cache::remember(
            'hamtala:initial_products',
            $ttl,
            fn () => $this->listProduct()
        );
    }

    /**
     * @throws HamtalaApiException
     * @throws ConnectionException
     */
    public function receiveOrder(array $order): array
    {
        $this->validateOrder($order);

        return $this->client->post('/api/v1/appLink/receive-order', [
            'order' => $order,
        ]);
    }

    public function confirmOrder(
        int $sourceOrderId,
        bool $confirmed,
        ?string $factorNumber = null,
        ?string $factorCode = null
    ): array {
        $payload = [
            'source_order_id' => $sourceOrderId,
            'confirmed'       => $confirmed,
        ];

        if ($confirmed) {
            $payload['factor_number'] = $factorNumber;
            $payload['factor_code'] = $factorCode;
        }

        return $this->client->post('/receive-order-confirmation', $payload);
    }

    /**
     * Inquire order status from Hamtala API
     *
     * @param int|null $orderId شناسه سفارش در سیستم API یا اپ شریک
     * @param int|null $sourceOrderId شناسه سفارش در سیستم اپ شریک
     * @param array|null $sourceOrderIds آرایه شناسه‌ها برای استعلام دسته‌ای (حداکثر ۵۰)
     * @return array
     * @throws \InvalidArgumentException
     * @throws HamtalaApiException
     */
    public function inquireOrderStatus(
        ?int $orderId = null,
        ?int $sourceOrderId = null,
        ?array $sourceOrderIds = null
    ): array {
        // Validation: exactly one parameter must be provided
        $providedParams = array_filter([
            'order_id' => $orderId,
            'source_order_id' => $sourceOrderId,
            'source_order_ids' => $sourceOrderIds,
        ], fn($value) => $value !== null);

        if (count($providedParams) !== 1) {
            throw new \InvalidArgumentException(
                'Exactly one of order_id, source_order_id, or source_order_ids must be provided.'
            );
        }

        // Validate source_order_ids count
        if ($sourceOrderIds !== null && count($sourceOrderIds) > 50) {
            throw new \InvalidArgumentException(
                'source_order_ids cannot contain more than 50 items.'
            );
        }

        $payload = array_filter([
            'order_id' => $orderId,
            'source_order_id' => $sourceOrderId,
            'source_order_ids' => $sourceOrderIds,
        ], fn($value) => $value !== null);

        return $this->client->post('/api/v1/appLink/inquire-order-status', $payload);
    }

    private function validateOrder(array $order): void
    {
        $required = [
            'product_id',
            'buy_or_sell',
            'order_type',
            'price',
            'mazane',
            'product_type',
            'product_name',
            'order_id',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $order)) {
                throw new HamtalaApiException("Missing required order field: {$field}");
            }
        }

        if (! in_array((int) $order['buy_or_sell'], [HamtalaConstants::BUY, HamtalaConstants::SELL], true)) {
            throw new HamtalaApiException('Invalid buy_or_sell value');
        }

        if (! in_array((int) $order['order_type'], [HamtalaConstants::ORDER_TYPE_NORMAL, HamtalaConstants::ORDER_TYPE_OTHER], true)) {
            throw new HamtalaApiException('Invalid order_type value');
        }

        if (! in_array((int) $order['product_type'], [HamtalaConstants::PRODUCT_TYPE_GOLD, HamtalaConstants::PRODUCT_TYPE_COIN], true)) {
            throw new HamtalaApiException('Invalid product_type value');
        }

        if ((int) $order['product_type'] === HamtalaConstants::PRODUCT_TYPE_GOLD && empty($order['weight'])) {
            throw new HamtalaApiException('Weight is required for gold orders');
        }

        if ((int) $order['product_type'] === HamtalaConstants::PRODUCT_TYPE_COIN && empty($order['count'])) {
            throw new HamtalaApiException('Count is required for coin orders');
        }
    }
}
