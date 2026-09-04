<?php

namespace App\Services;

use App\Enums\StockMode;
use App\Models\MetalOrder;
use App\Models\MetalOrderLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Random\RandomException;
use App\Models\Setting;
use Exception;
use Throwable;

class MetalOrderService
{
    /**
     * @throws RandomException
     */
    public static function generateTrackingCode(): int
    {
        do {
            // Generate a random 10-digit number (1,000,000,000 to 9,999,999,999)
            $trackingCode = random_int(1000000000, 9999999999);

            // Check if the tracking code already exists in the database
            $exists = DB::table('metal_orders')
                ->where('tracking_code', $trackingCode)->exists();
        } while ($exists);

        return $trackingCode;
    }

    /**
     * بروزرسانی سفارش با ثبت لاگ
     */
    public static function updateWithLogging(MetalOrder $metalOrder, array $attributes): MetalOrder
    {
        if (isset($attributes['extra_data'])) {
            $attributes['extra_data'] = is_string($attributes['extra_data'])
                ? json_decode($attributes['extra_data'], true)
                : $attributes['extra_data'];
        } else {
            $attributes['extra_data'] = [];
        }

        // بروزرسانی extra_data بر اساس وضعیت
        if (isset($attributes['status'])) {
            $attributes['extra_data'] = array_merge(
                $attributes['extra_data'],
                self::getExtraDataByStatus($attributes['status'], $metalOrder->extra_data ?? [])
            );
        }

        $original = $metalOrder->getOriginal();
        $previousStatus = $original['status'] ?? null;
        $metalOrder->fill($attributes);
        $dirty = $metalOrder->getDirty();
        $metalOrder->update($attributes);
        $changedAttributes = array_intersect_key($original, $dirty);
        $changes = Arr::except($metalOrder->getChanges(), ['updated_at']);

        if ($metalOrder->wasChanged()) {
            $isAuthenticated = Auth::check();

            MetalOrderLog::create([
                'metal_order_id' => $metalOrder->id,
                'new_values' => $changes,
                'old_values' => $changedAttributes,
                'loggable_id' => $isAuthenticated ? Auth::id() : null,
                'loggable_type' => $isAuthenticated ? array_search(get_class(Auth::user()), Relation::morphMap()) : null,
            ]);
        }

        if (
            $metalOrder->wasChanged('status') &&
            $metalOrder->status === 'succeed' &&
            $previousStatus !== 'succeed'
        ) {
            KimiaService::submitGoldOrder($metalOrder->fresh());
        }

        return $metalOrder->fresh();
    }

    /**
     * دریافت extra_data بر اساس وضعیت
     */
    private static function getExtraDataByStatus(string $status, array $existingExtraData): array
    {
        $extraData = [];
        $isAuthenticated = Auth::check();

        switch ($status) {
            case 'succeed':
                $extraData = array_filter([
                    'succeed_at' => Carbon::now(),
                    'succeed_by_id' => $isAuthenticated ? Auth::id() : null,
                    'succeed_by_name' => $isAuthenticated ? Auth::user()?->full_name : null,
                ]);
                break;

            case 'rejected':
                $extraData = array_filter([
                    'rejected_at' => Carbon::now(),
                    'reject_by_id' => $isAuthenticated ? Auth::id() : null,
                    'reject_by_name' => $isAuthenticated ? Auth::user()?->full_name : null,
                ]);
                break;
        }

        return array_merge($existingExtraData, $extraData);
    }

    /**
     * بروزرسانی وضعیت به موفق با لاگ
     */
    public static function markAsSucceed(MetalOrder $metalOrder, array $extraData = []): MetalOrder
    {
        $currentExtraData = $metalOrder->extra_data ?? [];

        $mergedExtraData = array_merge($currentExtraData, $extraData);

        return self::updateWithLogging($metalOrder, [
            'status' => 'succeed',
            'extra_data' => $mergedExtraData
        ]);
    }

    /**
     * @throws Throwable
     */
    public static function markAsSucceedAndUpdateStock(
        MetalOrder $metalOrder,
        array      $extraData = [],
        StockMode  $mode = StockMode::Accumulate
    ): void
    {
        DB::transaction(function () use ($metalOrder, $extraData, $mode) {
            self::markAsSucceed($metalOrder, $extraData);
            self::updateStock($metalOrder, $mode);
        });
    }

    /**
     * به‌روزرسانی موجودی melted_stock_quantity به‌صورت اتمیک.
     * سطر تنظیمات تا پایان تراکنش قفل می‌شود تا از race condition جلوگیری شود.
     *
     * توجه: اگر این متد خارج از یک تراکنش صدا زده شود، lockForUpdate بی‌اثر است.
     * برای رفتار قفل صحیح باید داخل DB::transaction اجرا شود.
     *
     * @throws Exception در صورت نبود تنظیمات یا نامعتبر بودن نوع سفارش
     */
    public static function updateStock(
        MetalOrder $metalOrder,
        StockMode  $mode = StockMode::Accumulate
    ): float
    {
        $setting = Setting::where('option_key', 'melted_stock_quantity')
            ->lockForUpdate()
            ->first();

        if (!$setting) {
            throw new Exception("Setting 'melted_stock_quantity' not found.");
        }

        if (!isset($metalOrder->product['quantity'])) {
            throw new Exception("Product quantity not found for metal order ID: {$metalOrder->id}");
        }

        $quantity = round((float)$metalOrder->product['quantity'], 3);
        $currentValue = round((float)$setting->option_value, 3);

        $calculatedStock = self::calculateStock($metalOrder, $currentValue, $quantity);
        $delta = $calculatedStock['delta'];

        // اعمال مقدار جدید stock
        $setting->option_value = $calculatedStock['new_stock'];
        $setting->save();

        // ذخیره delta در extra_data با حفظ مقادیر قبلی
        $extraData = $metalOrder->extra_data ?? [];
        $extraData['stock_delta'] = $delta;
        $metalOrder->extra_data = $extraData;
        $metalOrder->saveQuietly();

        return $delta;
    }

    /**
     * @throws Exception
     */
    private static function calculateStock(MetalOrder $metalOrder, float $current, float $quantity): array
    {
        if ($metalOrder->order_type == 'buy') {
            $autoQty = round((float)($metalOrder->extra_data['auto_order_quantity'] ?? 0), 3);
            $newStock = $current + ($autoQty - $quantity);

            return [
                'new_stock' => $newStock,
                'delta' => $current - $newStock,
            ];
        } elseif ($metalOrder->order_type == 'sell') {
            $autoQty = round((float)($metalOrder->extra_data['auto_order_quantity'] ?? 0), 3);
            $newStock = $current - ($autoQty - $quantity);

            return [
                'new_stock' => $newStock,
                'delta' => $current - $newStock,
            ];
        } else {
            throw new Exception("Invalid order type: {$metalOrder->order_type}");
        }
    }

    /**
     * @throws Throwable
     */
    public static function markAsRejectedAndRevertStock(
        MetalOrder $metalOrder,
        array      $extraData = []
    )
    {
        return DB::transaction(function () use ($metalOrder, $extraData) {
            self::markAsRejected($metalOrder, $extraData);
            self::revertStock($metalOrder);
        });
    }

    /**
     * برگرداندن stock به وضعیت قبل با استفاده از delta ذخیره‌شده.
     * باید داخل یک تراکنش فعال صدا زده شود.
     * @throws Exception
     */
    public static function revertStock(MetalOrder $metalOrder): ?float
    {
        $extraData = $metalOrder->extra_data ?? [];

        // اگر delta ثبت نشده باشد، چیزی برای برگرداندن نیست
        if (!isset($extraData['stock_delta'])) {
            return null;
        }

        $delta = round((float)$extraData['stock_delta'], 3);

        $setting = Setting::where('option_key', 'melted_stock_quantity')
            ->lockForUpdate()
            ->first();

        if (!$setting) {
            throw new Exception("Setting 'melted_stock_quantity' not found.");
        }

        $newValue = round((float)$setting->option_value + $delta, 3);
        $setting->option_value = $newValue;
        $setting->save();

        // علامت‌گذاری اینکه delta برگردانده شده تا از reverse دوباره جلوگیری شود
        $extraData['stock_reverted'] = true;
        $metalOrder->extra_data = $extraData;
        $metalOrder->save();

        return $newValue;
    }

    /**
     * بروزرسانی وضعیت به رد شده با لاگ
     */
    public static function markAsRejected(MetalOrder $metalOrder, array $extraData = []): void
    {
        if (!$metalOrder->fresh()->isFinalized()) {
            $currentExtraData = $metalOrder->extra_data ?? [];

            $mergedExtraData = array_merge($currentExtraData, $extraData);

            self::updateWithLogging($metalOrder, [
                'status' => 'rejected',
                'extra_data' => $mergedExtraData
            ]);
        }
    }
}
