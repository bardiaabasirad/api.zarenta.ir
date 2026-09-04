<?php

namespace App\Services\Hamtala;

use App\Models\MetalItemPriceSource;
use App\Models\RawMetalPrice;
use App\Models\SelectedMetalPrice;
use App\Services\MetalPriceIngestionService;
use Illuminate\Support\Facades\Log;

class HamtalaPriceService
{
    private const HAMTALA_SOURCE_ID = 5;

    public function handle(array $payload): void
    {
        if (($payload['event'] ?? null) !== 'price_updated') {
            return;
        }

        // پشتیبانی از هر دو ساختار: products در ریشه یا زیر data
        $rawProducts = $payload['products']
            ?? $payload['data']['products']
            ?? [];

        /**
         * تبدیل آرایه خام محصولات به کالکشن
         * نرمال‌سازی فیلدها و فیلتر کردن قیمت‌های صفر یا منفی به null
         * حذف آیتم‌های بدون شناسه
         * برگرداندن آرایه نهایی
         */
        $products = collect($rawProducts)
            ->map(function ($item) {
                // تابع کمکی برای اعتبارسنجی قیمت (فقط مقادیر بزرگ‌تر از صفر معتبر هستند)
                $sanitizePrice = fn($price) => (is_numeric($price) && $price > 0) ? $price : null;

                return [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'price_buy' => $sanitizePrice($item['price_buy'] ?? null),
                    'price_sell' => $sanitizePrice($item['price_sell'] ?? null),
                ];
            })
            ->filter(fn($item) => !empty($item['id']))
            ->values()
            ->all();

        $this->persistFromProducts($products);
    }

    public function updateFromApiResponse(array $apiProducts): void
    {
        $products = collect($apiProducts)
            ->map(fn($item) => [
                'id' => $item['product_id'] ?? null,
                'name' => $item['product_name'] ?? null,
                'unit' => null,
                'price_buy' => $item['price_buy'] ?? null,
                'price_sell' => $item['price_sell'] ?? null,
            ])
            ->filter(fn($item) => !empty($item['id']))
            ->values()
            ->all();

        Log::info('HamtalaPriceService: updating from API response.', [
            'count' => count($products),
        ]);

        $this->persistFromProducts($products);
    }

    /**
     * Persist Hamtala products through the ingestion service.
     * This replaces the old cache-based update flow.
     */
    private function persistFromProducts(array $products): void
    {
        app(MetalPriceIngestionService::class)->ingestFromHamtala($products);
    }

    /**
     * Get latest persisted prices from DB.
     *
     * Returns a structure compatible with the old cache payload as much as possible:
     * [
     *   'products' => [...],
     *   'product_ids' => [...],
     *   'setting' => null,
     *   'updated_at' => '...'
     * ]
     */
    public function getLatestPrices(): ?array
    {
        $products = $this->getLatestProductsQuery()
            ->get(['id', 'metal_item_id', 'price_source_id', 'buy', 'sell', 'time', 'updated_at'])
            ->map(fn($row) => [
                'id' => (int)$row->metal_item_id,
                'name' => null,
                'unit' => null,
                'price_buy' => $row->sell,
                'price_sell' => $row->buy,
                'price_source_id' => $row->price_source_id,
                'time' => $row->time,
                'updated_at' => $row->updated_at,
            ])
            ->values();

        if ($products->isEmpty()) {
            return null;
        }

        return [
            'products' => $products->all(),
            'product_ids' => $products->pluck('id')->filter()->unique()->values()->all(),
            'setting' => null,
            'updated_at' => $this->getLastUpdatedAt(),
        ];
    }

    public function getLastUpdatedAt(): ?string
    {
        $updatedAt = SelectedMetalPrice::query()
            ->where('price_source_id', self::HAMTALA_SOURCE_ID)
            ->max('updated_at');

        return $updatedAt ? (string)$updatedAt : null;
    }

    public function getProductPrice(int $metal_item_id): ?array
    {
        $row = $this->getLatestProductsQuery()
            ->where('metal_item_id', $metal_item_id)
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->first(['id', 'metal_item_id', 'price_source_id', 'buy', 'sell', 'time', 'updated_at']);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row->metal_item_id,
            'raw_id' => (int)$row->id,
            'name' => null,
            'unit' => null,
            'price_buy' => $row->sell,
            'price_sell' => $row->buy,
            'price_source_id' => $row->price_source_id,
            'time' => $row->time,
            'updated_at' => $row->updated_at,
        ];
    }

    private function getLatestProductsQuery()
    {
        $latestIds = RawMetalPrice::query()
            ->where('price_source_id', self::HAMTALA_SOURCE_ID)
            ->selectRaw('MAX(id) as id')
            ->groupBy('metal_item_id')->get();

        return RawMetalPrice::query()
            ->where('price_source_id', self::HAMTALA_SOURCE_ID)
            ->whereIn('id', $latestIds);
    }
}
