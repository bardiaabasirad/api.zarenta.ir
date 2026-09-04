<?php

namespace App\Services;

use App\Events\AggregatedRateUpdated;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class UserRateNotificationService
{
    public static function notify(SelectedMetalPrice $selected_metal_price)
    {
        // انتخاب bucket؛ اینجا هر دقیقه
        $bucket = Carbon::parse($selected_metal_price->created_at)->format('Y-m-d H:i'); // e.g. "2025-08-09 08:01"
        $key = "rates:agg:{$bucket}";

        // استفاده از Redis hash برای ذخیره aggregation: sum, count, min, max
        $redis = Redis::connection();

        // ابتدا مقدارهای sum و count را افزایشی به‌روزرسانی می‌کنیم
        // HINCRBYFLOAT برای sum، HINCRBY برای count
        $redis->hincrbyfloat($key, 'sum', (float)$selected_metal_price->sell);
        $redis->hincrby($key, 'count', 1);

        // min و max را با Lua یا متد ساده کنترل می‌کنیم:
        // ساده‌ترین: get old min/max و مقایسه و set اگر نیاز
        $oldMin = $redis->hget($key, 'min');
        $oldMax = $redis->hget($key, 'max');

        if ($oldMin === null || (float)$selected_metal_price->sell < (float)$oldMin) {
            $redis->hset($key, 'min', (float)$selected_metal_price->sell);
        }
        if ($oldMax === null || (float)$selected_metal_price->sell > (float)$oldMax) {
            $redis->hset($key, 'max', (float)$selected_metal_price->sell);
        }

        // set expiration تا بعد از مثلاً 2 روز bucket پاک شود
        $redis->expire($key, 60 * 60 * 24);

        // حالا محاسبه میانگین (sum / count)
        $sum = (float)$redis->hget($key, 'sum');
        $count = (int)$redis->hget($key, 'count');
        $min = (float)$redis->hget($key, 'min');
        $max = (float)$redis->hget($key, 'max');

        $avg = $count > 0 ? $sum / $count : null;

        // timestamp برای محور x: بهتر iso string یا timestamp
        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $bucket)->toIso8601String();

        $marketStatus = Cache::rememberForever('market_status', function () {
            return Setting::where('option_key', 'market_status')->value('option_value');
        });

        // broadcast event با مقدار تجمیع‌شده فعلی
        event(new AggregatedRateUpdated($bucket, ceil($avg), $min, $max, $selected_metal_price->sell, $selected_metal_price->buy, $count, $timestamp, $selected_metal_price->priceSource, $marketStatus));
    }
}
