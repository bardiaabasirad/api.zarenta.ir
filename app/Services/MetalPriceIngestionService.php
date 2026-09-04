<?php

namespace App\Services;

use App\Models\PriceSourceMapping;
use App\Models\RawMetalPrice;
use App\Models\SelectedMetalPrice;
use App\Observers\RawMetalPriceObserver;
use App\Observers\SelectedMetalPriceObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetalPriceIngestionService
{
    private const HAMTALA_SOURCE_ID = 5;

    /**
     * ثبت bulk و فوری نرخ‌های Hamtala (بدون صف).
     */
    public function ingestFromHamtala(array $products): void
    {
        $sourceId = self::HAMTALA_SOURCE_ID;

        // map: external_identifier (string) => [metal_item_id, ...]
        $externalToMetalMap = $this->loadExternalToMetalMappings($sourceId);
        if (!$externalToMetalMap) {
            return;
        }

        // map: metal_item_id => PriceSourceMapping
        $selectionMappings = $this->loadSelectionMappings($sourceId);

        // ✅ Pre-load: همه metal_item_id های درگیر
        $allMetalItemIds = collect($externalToMetalMap)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        // ✅ Pre-load: آخرین selected price هر آیتم در یک query
        // ترتیب صعودی بر اساس time تا keyBy آخرین (جدیدترین) رکورد را نگه دارد،
        // معادل رفتار latest()->first() کد اصلی.
        $latestPrices = SelectedMetalPrice::whereIn('metal_item_id', $allMetalItemIds)
            ->orderBy('time')
            ->orderBy('id')
            ->get(['id', 'metal_item_id', 'price_source_id', 'time'])
            ->keyBy('metal_item_id');

        $now = now();

        $rawPricesToInsert = [];
        $selectedJobs = []; // داده‌های لازم برای ساخت selected price

        foreach ($products as $product) {
            $externalProductId = $product['id'] ?? null;
            if ($externalProductId === null) {
                continue;
            }

            $metalItemIds = $externalToMetalMap[(string)$externalProductId] ?? [];
            if (!$metalItemIds) {
                continue;
            }

            // buy/sell به صورت معکوس (مطابق منطق اصلی storeRawPrice)
            $rawBuy = $product['price_sell'] ?? null;
            $rawSell = $product['price_buy'] ?? null;

            foreach ($metalItemIds as $metalItemId) {
                // ✅ آماده‌سازی raw price برای bulk insert
                $rawPricesToInsert[] = [
                    'price_source_id' => $sourceId,
                    'metal_item_id' => $metalItemId,
                    'buy' => $rawBuy,
                    'sell' => $rawSell,
                    'time' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $priceSourceMapping = $selectionMappings[$metalItemId] ?? null;
                if (!$priceSourceMapping) {
                    continue;
                }

                // همان شرط اصلی: اگر آخرین selected price وجود دارد و
                // price_source_id آن null است، selected جدید نساز.
                $latestPrice = $latestPrices->get($metalItemId);
                if ($latestPrice !== null && $latestPrice->price_source_id === null) {
                    continue;
                }

                $selectedJobs[] = [
                    'metal_item_id' => $metalItemId,
                    'mapping' => $priceSourceMapping,
                    'raw_buy' => $rawBuy,
                    'raw_sell' => $rawSell,
                ];
            }
        }

        if (empty($rawPricesToInsert) && empty($selectedJobs)) {
            return;
        }

        // ✅ همه چیز در یک transaction برای consistency
        DB::transaction(function () use ($rawPricesToInsert, $selectedJobs, $now) {
            if (!empty($rawPricesToInsert)) {
                RawMetalPrice::insert($rawPricesToInsert);
                app(RawMetalPriceObserver::class)->afterBulkInsert($rawPricesToInsert);
            }

            $this->processSelectedPrices($selectedJobs, $now);
        });

        // ✅ پاکسازی یکجای کش‌های latest_price
        foreach ($allMetalItemIds as $metalItemId) {
            Cache::forget("latest_price_{$metalItemId}");
        }
    }

    /**
     * ساخت و bulk insert نرخ‌های انتخابی با حفظ کامل منطق فرمول و تلرانس.
     * این منطق دقیقاً معادل SelectedMetalPriceService::createRate است.
     */
    private function processSelectedPrices(array $selectedJobs, $time): void
    {
        if (empty($selectedJobs)) {
            return;
        }

        $selectedPricesToInsert = [];

        foreach ($selectedJobs as $job) {
            /** @var PriceSourceMapping $mapping */
            $mapping = $job['mapping'];
            $rawBuy = $job['raw_buy'];
            $rawSell = $job['raw_sell'];

            $variables = [
                'buy' => $rawBuy,
                'sell' => $rawSell,
            ];

            if ($mapping) {
                // فرمول پایه
                $buy = $rawBuy
                    ? roundUpToThousand(
                        calculateFormula(
                            str_replace('fee', 'buy', $mapping->buy),
                            $variables
                        )
                    )
                    : null;

                $sell = $rawSell
                    ? roundUpToThousand(
                        calculateFormula(
                            str_replace('fee', 'sell', $mapping->sell),
                            $variables
                        )
                    )
                    : null;

                // منطق تلرانس / تولید یک طرف از طرف دیگر
                if ($mapping->generate_buy_or_sell == 'active' && (!$buy || !$sell)) {
                    if (!$buy && $mapping->buy_from_sell) {
                        $buy = roundUpToThousand(
                            calculateFormula(
                                str_replace('fee', 'sell', $mapping->buy_from_sell),
                                $variables
                            )
                        );
                    }

                    if (!$sell && $mapping->sell_from_buy) {
                        $sell = roundUpToThousand(
                            calculateFormula(
                                str_replace('fee', 'buy', $mapping->sell_from_buy),
                                $variables
                            )
                        );
                    }
                }

                $priceSourceId = $mapping->price_source_id;
            } else {
                // حالت بدون mapping
                $buy = $rawBuy;
                $sell = $rawSell;
                $priceSourceId = self::HAMTALA_SOURCE_ID;
            }

            $selectedPricesToInsert[] = [
                'price_source_id' => $priceSourceId,
                'metal_item_id' => $job['metal_item_id'],
                'buy' => $buy,
                'sell' => $sell,
                'time' => $time,
                'created_at' => $time,
                'updated_at' => $time,
            ];
        }

        // ✅ Bulk insert نرخ‌های انتخابی در یک query
        if (!empty($selectedPricesToInsert)) {
            SelectedMetalPrice::insert($selectedPricesToInsert);

            // Fetch inserted records with IDs
            $insertedRecords = SelectedMetalPrice::whereIn('metal_item_id', array_column($selectedPricesToInsert, 'metal_item_id'))
                ->where('created_at', '>=', now()->subSeconds(5))
                ->get()
                ->keyBy(fn($p) => $p->metal_item_id . '_' . $p->price_source_id);

            // Enrich data with IDs
            foreach ($selectedPricesToInsert as &$item) {
                $key = $item['metal_item_id'] . '_' . $item['price_source_id'];
                if ($record = $insertedRecords->get($key)) {
                    $item['id'] = $record->id;
                }
            }

            app(SelectedMetalPriceObserver::class)->afterBulkInsert($selectedPricesToInsert);
        }
    }

    /**
     * map: external_identifier (string) => [metal_item_id, ...]
     */
    private function loadExternalToMetalMappings(int $sourceId): array
    {
        /**
         * [
         * '101' => [5, 12, 18],
         * '205' => [7],
         * '310' => [3, 9, 15, 22],
         * ]
         *
         * یعنی یک آرایه که کلیدش rate_external_identifier و مقدارش آرایه‌ای از metal_item_id ها است.
         * */
        return DB::table('metal_item_price_source')
            ->where('price_source_id', $sourceId)
            ->whereNotNull('rate_external_identifier')
            ->get(['metal_item_id', 'rate_external_identifier'])
            ->groupBy(fn($row) => (string)$row->rate_external_identifier)
            ->map(fn($group) => $group->pluck('metal_item_id')->map(fn($v) => (int)$v)->all())
            ->all();
    }

    /**
     * map: metal_item_id => PriceSourceMapping
     */
    private function loadSelectionMappings(int $sourceId): array
    {
        /**
         * [
         *      1 => PriceSourceMapping {
         *          id: 1,
         *          price_source_id: 4,
         *          metal_item_id: 1,
         *          buy: "floor(fee/10000)*10000",
         *          buy_from_sell: "fee",
         *          sell: "ceil(fee/10000)*10000",
         *          sell_from_buy: "fee+160000",
         *          generate_buy_or_sell: 0,
         *          created_at: "...",
         *          updated_at: "...",
         *      },
         *      2 => PriceSourceMapping {
         *          id: 2,
         *          price_source_id: 4,
         *          metal_item_id: 2,
         *          buy: "floor(fee/10000)*10000",
         *          buy_from_sell: "fee",
         *          sell: "ceil(fee/10000)*10000",
         *          sell_from_buy: "fee-50000",
         *          generate_buy_or_sell: 0,
         *          created_at: "...",
         *          updated_at: "...",
         *      },
         * ]
         */
        return PriceSourceMapping::where('price_source_id', $sourceId)
            ->get()
            ->keyBy('metal_item_id')
            ->all();
    }
}
