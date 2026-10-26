<?php

namespace App\Services;

use App\Models\MetalItem;
use App\Models\Unit;

/**
 * محاسبه نرخ نمایشی و نرخ واحد فیزیکی برای سفارش فلزات
 */
class MetalPriceQuoteService
{
    public function __construct(
        private readonly MetalPriceService $priceService
    ) {
    }

    /**
     * @param  object{buy: mixed, sell: mixed}  $price
     * @param  object{target_unit_id?: string, display_mode?: string, tolerance_type?: string, buy_fee_margin?: mixed, sell_fee_margin?: mixed}  $config
     */
    public function quote(object $price, object $config, string $action, MetalItem $metalItem): array
    {
        if ($action === 'buy') {
            $raw = $price->sell;
            $tolerance = $config->sell_fee_margin ?? 0;
        } else {
            $raw = $price->buy;
            $tolerance = $config->buy_fee_margin ?? 0;
        }

        $base = ($config->tolerance_type ?? 'fixed_amount') == 'fixed_amount'
            ? $raw + $tolerance
            : $raw + ($raw * $tolerance / 100);

        $pricingUnitId = $metalItem->pricing_unit_id ?? 'mazane';
        $targetUnitId = $config->target_unit_id
            ?? $this->priceService->fromLegacyDisplayMode($config->display_mode ?? 'quotation');
        $physicalUnit = $metalItem->unit ?? 'gram';
        $baseStr = (string) $base;

        if ($physicalUnit === 'count' || $pricingUnitId === 'count') {
            $rounded = $action == 'buy'
                ? roundUpToThousand($base)
                : roundDownToThousand((int) $base);
            $displayed = $rounded;
            $fee = $rounded;
        } else {
            $perPhysical = $this->priceService->pricePerPhysicalUnit(
                $baseStr,
                $pricingUnitId,
                $physicalUnit
            );

            if ($targetUnitId === 'gram') {
                $rounded = $action === 'buy'
                    ? roundUpToThousand((float) $perPhysical)
                    : roundDownToThousand((int) floor((float) $perPhysical));
                $displayed = $rounded;
                $fee = (int) round((float) $this->priceService->convert(
                    (string) $rounded,
                    'gram',
                    $pricingUnitId,
                    6
                ));
            } else {
                $rounded = (float) $perPhysical;
                $displayed = (int) round((float) $this->priceService->convert(
                    $baseStr,
                    $pricingUnitId,
                    $targetUnitId,
                    6
                ));
                $fee = $displayed;
                if ($targetUnitId !== $pricingUnitId) {
                    $fee = (int) round((float) $this->priceService->convert(
                        (string) $displayed,
                        $targetUnitId,
                        $pricingUnitId,
                        6
                    ));
                }
            }
        }

        return [
            'raw' => $raw,
            'tolerance' => $tolerance,
            'base' => $base,
            'rounded' => $rounded,
            'displayed' => $displayed,
            'fee' => $fee,
            'pricing_unit_id' => $pricingUnitId,
            'target_unit_id' => $targetUnitId,
            'target_unit_name' => Unit::cached($targetUnitId)?->name,
            'display_mode' => $this->priceService->toLegacyDisplayMode($targetUnitId),
        ];
    }
}
