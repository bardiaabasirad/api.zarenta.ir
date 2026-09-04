<?php

namespace App\Observers;

use App\Events\AdminSelectedMetalPriceUpdated;
use App\Events\BoardMetalPriceUpdated;
use App\Events\SelectedMetalPriceUpdated;
use App\Jobs\DispatchMetalPriceWebhooks;
use App\Models\BoardCoin;
use App\Models\MetalItem;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\BoardCoinService;
use App\Services\UserRateNotificationService;
use DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SelectedMetalPriceObserver
{
    public function created(SelectedMetalPrice $price): void
    {
        try {
            SelectedMetalPriceUpdated::dispatch($price);

            $price->load([
                'priceSource' => fn ($query) => $query->select(['id', 'name']),
                'metalItem'   => fn ($query) => $query->select(['id', 'title', 'is_visible'])
            ]);

            AdminSelectedMetalPriceUpdated::dispatch($price);

            if ($price->metalItem && (bool) $price->metalItem->is_visible) {
                $payload = $price->only(['id', 'metal_item_id', 'buy', 'sell']);
                $payload['created_at'] = $price->created_at?->toISOString();
                $payload['updated_at'] = $price->updated_at?->toISOString();
                $payload['product'] = $price->metalItem->title;

                DispatchMetalPriceWebhooks::dispatch($payload)
                    ->afterCommit();
            }


            $moltenPageMetalItem = Cache::rememberForever(
                'setting.molten_page_metal_item_id',
                fn () => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
            );

            if ($price->metal_item_id == $moltenPageMetalItem->option_value) {
                UserRateNotificationService::notify($price);
            }

            // گارد: آیا این نرخ متعلق به یکی از سکه‌های بُرد است؟
            $coinMetalIds = Cache::rememberForever('board_coin_metal_ids', function () {
                return BoardCoin::pluck('metal_item_id')->all();
            });

            if (! in_array($price->metal_item_id, $coinMetalIds)) {
                return;
            }

            // بازسازی کل بُرد و broadcast
            $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

            BoardMetalPriceUpdated::dispatch($coins);
        } catch (\Exception $exception) {
            Log::error('SelectedMetalPriceObserver created: ' . $exception->getMessage());
        }
    }

    /**
     * فراخوانی دستی بعد از bulk insert
     *
     * @param array $insertedData
     * @return void
     */
    public function afterBulkInsert(array $insertedData): void
    {
        if (empty($insertedData)) {
            return;
        }

        // یک بار query برای price_sources
        $priceSourceIds = collect($insertedData)->pluck('price_source_id')->unique()->filter();
        $priceSources = DB::table('price_sources')
            ->whereIn('id', $priceSourceIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // یک بار query برای metal_items (فقط رکوردهایی که is_visible برابر true دارند)
        $metalItemIds = collect($insertedData)
            ->pluck('metal_item_id')
            ->unique()
            ->filter();

        $metalItems = MetalItem::query()
            ->whereIn('id', $metalItemIds)
            ->where('is_visible', true)
            ->select(['id', 'title', 'is_visible'])
            ->get()
            ->keyBy('id');

        // گروه‌بندی بر اساس metal_item_id
        $grouped = collect($insertedData)->groupBy('metal_item_id');

        $moltenPageMetalItem = Cache::rememberForever(
            'setting.molten_page_metal_item_id',
            fn () => Setting::firstWhere('option_key', 'molten_page_metal_item_id')
        );

        $moltenPageMetalItemId = $moltenPageMetalItem?->option_value;

        $boardCoinMetalIds = Cache::remember(
            'board_coin_metal_ids',
            now()->addHours(24),
            fn () => BoardCoin::pluck('metal_item_id')->toArray()
        );

        $needsBoardRebuild = false;

        foreach ($grouped as $metalItemId => $group) {
            // فقط آخرین نرخ هر metal_item_id
            $lastPrice = $group->last();

            // اطمینان حاصل کن که آرایه است
            if ($lastPrice instanceof \Illuminate\Support\Collection) {
                $lastPrice = $lastPrice->toArray();
            } elseif (is_object($lastPrice)) {
                $lastPrice = (array) $lastPrice;
            }

            if (empty($lastPrice['id'])) {
                Log::warning('afterBulkInsert: missing id for metal_item_id ' . $metalItemId);
                continue;
            }

            $price = new SelectedMetalPrice($lastPrice);
            $price->id = $lastPrice['id'];
            $price->exists = true;

            // Load priceSource relation
            if ($priceSource = $priceSources->get($lastPrice['price_source_id'])) {
                $price->setRelation('priceSource', $priceSource);
            }

            // Load metalItem relation بدون کوئری اضافه
            if ($metalItem = $metalItems->get($metalItemId)) {
                $price->setRelation('metalItem', $metalItem);
            }

            // Dispatch events
            SelectedMetalPriceUpdated::dispatch($price);
            AdminSelectedMetalPriceUpdated::dispatch($price);

            if ($metalItems->has($metalItemId)) {
                DispatchMetalPriceWebhooks::dispatch(
                    $price->only([
                        'id',
                        'metal_item_id',
                        'buy',
                        'sell',
                        'created_at',
                        'updated_at',
                    ]) + [
                        'product' => $price->metalItem->title,
                    ]
                )
                    ->afterCommit();
            }

            // Check molten page notification
            if ($moltenPageMetalItemId && $metalItemId == $moltenPageMetalItemId) {
                app(UserRateNotificationService::class)->notify($price);
            }

            // Check board coins
            if (in_array($metalItemId, $boardCoinMetalIds)) {
                $needsBoardRebuild = true;
            }
        }

        // یک بار board را rebuild می‌کنیم
        if ($needsBoardRebuild) {
            $this->rebuildAndBroadcastBoard();
        }
    }


    /**
     * Rebuild board و broadcast
     */
    private function rebuildAndBroadcastBoard(): void
    {
        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();
        BoardMetalPriceUpdated::dispatch($coins);
    }

}
