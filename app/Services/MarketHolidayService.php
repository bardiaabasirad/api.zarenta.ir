<?php

namespace App\Services;

use App\Models\MarketHoliday;
use App\Models\MetalItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MarketHolidayService
{
    /**
     * محاسبه تاریخ/زمان تسویه حساب بر اساس تعداد روز کاری آینده.
     *
     * @param  MetalItem            $metalItem
     * @param  CarbonInterface|null $from  تاریخ مبنا (پیش‌فرض: الان)
     * @return Carbon
     */
    public static function getSettlementDateForMetalItem(MetalItem $metalItem, ?CarbonInterface $from = null): Carbon
    {
        $days = (int) $metalItem->settlement_working_days;

        // اگر 0 یا منفی بود، همان تاریخ مبنا را برگردان (یا اگر سیاست شما چیز دیگری است بگو)
        $cursor = Carbon::instance(($from ? Carbon::instance($from) : now()));

        if ($days <= 0) {
            return $cursor;
        }

        /**
         * برای جلوگیری از N+1 و کوئری‌های متعدد:
         * یک بازه معقول از تعطیلات را یکجا می‌گیریم.
         * به طور تقریبی برای n روز کاری، ممکن است تا ~ n + تعطیلات + جمعه‌ها جلو برویم.
         * اینجا یک بافر امن می‌گذاریم.
         */
        $bufferDays = max(14, $days * 3); // قابل تنظیم بر اساس واقعیت کسب‌وکار
        $start = $cursor->copy()->toDateString();
        $end   = $cursor->copy()->addDays($bufferDays)->toDateString();

        $holidayDates = MarketHoliday::query()
            ->whereBetween('date', [$start, $end])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip(); // برای lookup سریع: isset($holidayDates[$date])

        $addedWorkingDays = 0;

        while ($addedWorkingDays < $days) {
            $cursor->addDay();

            if (self::isNonWorkingDay($cursor, $holidayDates)) {
                continue;
            }

            $addedWorkingDays++;
        }

        return $cursor;
    }

    /**
     * تشخیص غیرکاری بودن یک روز:
     * - جمعه‌ها همیشه تعطیل
     * - تاریخ‌های جدول market_holidays تعطیل
     *
     * @param CarbonInterface $date
     * @param Collection|\ArrayAccess|array $holidayDates
     * @return bool
     */
    protected static function isNonWorkingDay(CarbonInterface $date, $holidayDates): bool
    {
        // جمعه در Carbon: FRIDAY
        if ($date->dayOfWeek === Carbon::FRIDAY) {
            return true;
        }

        $key = $date->toDateString();

        // چون flip کردیم، وجود کلید یعنی تعطیل
        return isset($holidayDates[$key]);
    }
}
