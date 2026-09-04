<?php

namespace App\Observers;

use App\Events\LatestRawMetalPriceUpdated;
use App\Models\RawMetalPrice;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RawMetalPriceObserver
{
    public function created(RawMetalPrice $rawMetalPrice): void
    {
        try {
            // بارگذاری relation فقط یک بار
            $rawMetalPrice->load([
                'priceSource',
                'metalItem' => function ($query) {
                    $query->withoutGlobalScope('visible')
                        ->select('id', 'title');
                }
            ]);

            // بررسی و ثبت اولین نرخ روز
            $this->checkAndNotifyFirstPriceOfDay($rawMetalPrice);

            // بررسی شرایط اصلی برای dispatch کردن event
            if ($this->shouldDispatchPriceEvent($rawMetalPrice)) {
                LatestRawMetalPriceUpdated::dispatch($rawMetalPrice);
            }
        }
        catch (\Throwable $exception) {
            Log::error("RawMetalPriceObserver created: " . $exception->getMessage());
        }
    }

    private function checkAndNotifyFirstPriceOfDay(RawMetalPrice $rawMetalPrice): void
    {
        try {
            if (!$rawMetalPrice->priceSource) {
                return;
            }

            $today = now()->startOfDay();

            // بررسی اینکه آیا نرخی برای این کانال امروز ثبت شده است یا نه
            $isFirstPriceOfDay = RawMetalPrice::where('price_source_id', $rawMetalPrice->price_source_id)
                ->where('metal_item_id', $rawMetalPrice->metal_item_id)
                ->whereDate('created_at', $today)
                ->where('id', '<', $rawMetalPrice->id)
                ->exists();

            // اگر این اولین نرخ امروز است، notification ثبت کن
            if (! $isFirstPriceOfDay) {
                $priceSourceName = $rawMetalPrice->priceSource->name ?? 'صرافی';
                $typeName = $rawMetalPrice->metalItem->title;

                $service = app(NotificationService::class);

                $service->createPublicSingleRead([
                    'title' => 'شروع روز کاری',
                    'content' => "نرخ {$typeName} {$priceSourceName} باز شد.",
                    'created_by' => null,
                ]);
            }
        }
        catch (\Throwable $exception) {
            Log::error("RawMetalPriceObserver checkAndNotifyFirstPriceOfDay: " . $exception->getMessage());
        }
    }

    /**
     * بررسی شرایط برای dispatch کردن event نرخ اعمال‌نشده
     */
    private function shouldDispatchPriceEvent(RawMetalPrice $rawMetalPrice): bool
    {
        // اگر price_source_id نرخ جدید null است، event نفرست
        if (is_null($rawMetalPrice->price_source_id)) {
            return false;
        }

        // بررسی اینکه کانال جدید در price_source_mappings وجود دارد
        $channelExists = DB::table('price_source_mappings')
            ->where('metal_item_id', $rawMetalPrice->metal_item_id)
            ->where('price_source_id', $rawMetalPrice->price_source_id)
            ->exists();

        if (! $channelExists) {
            return false;
        }

        // تمام شرایط برقرار است، event بفرست
        return true;
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

        // گروه‌بندی بر اساس price_source_id و metal_item_id
        $grouped = collect($insertedData)->groupBy(function ($item) {
            return $item['price_source_id'] . '_' . $item['metal_item_id'];
        });

        // یک بار query برای همه price_source_ids
        $priceSourceIds = collect($insertedData)->pluck('price_source_id')->unique()->filter();
        $priceSources = DB::table('price_sources')
            ->whereIn('id', $priceSourceIds)
            ->get()
            ->keyBy('id');

        // یک بار query برای همه metal_item_ids
        $metalItemIds = collect($insertedData)->pluck('metal_item_id')->unique()->filter();
        $metalItems = DB::table('metal_items')
            ->whereIn('id', $metalItemIds)
            ->get(['id', 'title'])
            ->keyBy('id');

        // یک بار query برای check first price of day
        $today = now()->startOfDay();
        $existingPairs = DB::table('raw_metal_prices')
            ->whereDate('created_at', $today)
            ->whereIn('price_source_id', $priceSourceIds)
            ->whereIn('metal_item_id', $metalItemIds)
            ->select('price_source_id', 'metal_item_id')
            ->distinct()
            ->get()
            ->map(fn($row) => $row->price_source_id . '_' . $row->metal_item_id)
            ->flip();

        // یک بار query برای price_source_mappings
        $validMappings = DB::table('price_source_mappings')
            ->whereIn('price_source_id', $priceSourceIds)
            ->pluck('price_source_id')
            ->flip();

        foreach ($grouped as $key => $group) {
            $first = $group->first();
            $priceSourceId = $first['price_source_id'];
            $metalItemId = $first['metal_item_id'];

            // Check first price of day
            if (!isset($existingPairs[$key])) {
                $priceSource = $priceSources->get($priceSourceId);
                $metalItem = $metalItems->get($metalItemId);

                if ($priceSource && $metalItem) {
                    app(NotificationService::class)->createNotification(
                        title: 'شروع روز کاری',
                        content: "نرخ {$metalItem->title} {$priceSource->name} باز شد.",
                        isPublic: true
                    );
                }
            }

            // Dispatch event اگر mapping وجود داشته باشد
            if (isset($validMappings[$priceSourceId])) {
                // فقط آخرین نرخ را dispatch می‌کنیم
                $lastPrice = $group->last();

                // اطمینان حاصل کن که آرایه است
                if ($lastPrice instanceof \Illuminate\Support\Collection) {
                    $lastPrice = $lastPrice->toArray();
                } elseif (is_object($lastPrice)) {
                    $lastPrice = (array) $lastPrice;
                }

                $rawMetalPrice = new RawMetalPrice($lastPrice);
                $rawMetalPrice->exists = true;

                // Load relations
                if ($priceSource = $priceSources->get($priceSourceId)) {
                    $rawMetalPrice->setRelation('priceSource', (object)$priceSource);
                }
                if ($metalItem = $metalItems->get($metalItemId)) {
                    $rawMetalPrice->setRelation('metalItem', (object)$metalItem);
                }

                LatestRawMetalPriceUpdated::dispatch($rawMetalPrice);
            }
        }
    }
}
