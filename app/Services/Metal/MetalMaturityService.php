<?php

namespace App\Services\Metal;

use App\Constants\MetalMaturity;
use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Services\MarketHolidayService;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class MetalMaturityService
{
    public function codeForMetalItem(MetalItem $metalItem): string
    {
        return MetalMaturity::fromWorkingDays((int) $metalItem->settlement_working_days);
    }

    public function codeForOrder(MetalOrder $order): string
    {
        if ($order->relationLoaded('metalItem') && $order->metalItem) {
            return $this->codeForMetalItem($order->metalItem);
        }

        $item = MetalItem::withoutGlobalScope('visible')->find($order->metal_item_id);

        if ($item) {
            return $this->codeForMetalItem($item);
        }

        if ($order->settlement_date) {
            $days = Carbon::today(config('app.timezone'))
                ->diffInDays(Carbon::parse($order->settlement_date), false);

            return MetalMaturity::fromWorkingDays(max(0, (int) $days));
        }

        return MetalMaturity::T0;
    }

    public function settlementDateForItem(MetalItem $metalItem, ?CarbonInterface $from = null): Carbon
    {
        return MarketHolidayService::getSettlementDateForMetalItem($metalItem, $from);
    }

    public function ensureOrderSettlement(MetalOrder $order): MetalOrder
    {
        if ($order->settlement_date) {
            return $order;
        }

        $item = MetalItem::withoutGlobalScope('visible')->find($order->metal_item_id);

        if (!$item) {
            return $order;
        }

        $date = $this->settlementDateForItem($item);
        $order->settlement_date = $date->toDateString();
        $order->settlement_status = $date->isAfter(today()) ? 'pending' : 'settled';
        $order->save();

        return $order->fresh();
    }

    public function coverageDeadlineForOrder(MetalOrder $order): ?string
    {
        if ($order->settlement_date) {
            return Carbon::parse($order->settlement_date)->toDateString();
        }

        $item = MetalItem::withoutGlobalScope('visible')->find($order->metal_item_id);

        if (!$item) {
            return null;
        }

        return $this->settlementDateForItem($item)->toDateString();
    }

    /**
     * @return list<string>
     */
    public function allowedSourceMaturities(string $orderMaturity): array
    {
        return MetalMaturity::allowedSourceMaturities($orderMaturity);
    }

    public function isSourceAllowed(string $orderMaturity, string $sourceMaturity): bool
    {
        return in_array($sourceMaturity, $this->allowedSourceMaturities($orderMaturity), true);
    }
}
