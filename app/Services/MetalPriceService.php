<?php

namespace App\Services;

use App\Models\Unit;
use InvalidArgumentException;

class MetalPriceService
{
    private const BC_SCALE = 8;

    /**
     * تبدیل نرخ بین دو واحد وزنی:
     * Display = Base × (targetMultiplier / sourceMultiplier)
     */
    public function convert(
        string $price,
        string $sourceUnitId,
        string $targetUnitId,
        int $scale = 4
    ): string {
        if ($sourceUnitId === $targetUnitId) {
            return $this->normalize($price);
        }

        $source = Unit::cached($sourceUnitId);
        $target = Unit::cached($targetUnitId);

        if (! $source || ! $target) {
            throw new InvalidArgumentException("واحد نامعتبر: {$sourceUnitId} → {$targetUnitId}");
        }

        if (! $source->is_weight || ! $target->is_weight) {
            // واحدهای غیر وزنی (مثل عدد) قابل تبدیل وزنی نیستند
            if ($sourceUnitId === $targetUnitId) {
                return $this->normalize($price);
            }

            throw new InvalidArgumentException("تبدیل بین واحدهای غیر وزنی پشتیبانی نمی‌شود.");
        }

        $sourceMultiplier = (string) $source->multiplier_to_gram;
        $targetMultiplier = (string) $target->multiplier_to_gram;

        if (bccomp($sourceMultiplier, '0', self::BC_SCALE) === 0) {
            throw new InvalidArgumentException("ضریب واحد مبدا صفر است.");
        }

        $numerator = bcmul($price, $targetMultiplier, self::BC_SCALE);

        return $this->normalize(bcdiv($numerator, $sourceMultiplier, $scale));
    }

    /**
     * نرخ نمایشی گروه برای کاربر (با گرد کردن اختیاری به هزار)
     */
    public function calculateDisplayPrice(
        string $price,
        string $sourceUnitId,
        string $targetUnitId,
        ?string $roundDirection = null,
        int $scale = 4
    ): string {
        $converted = $this->convert($price, $sourceUnitId, $targetUnitId, $scale);

        if ($roundDirection === 'up') {
            return (string) roundUpToThousand((float) $converted);
        }

        if ($roundDirection === 'down') {
            return (string) roundDownToThousand((int) floor((float) $converted));
        }

        return $converted;
    }

    /**
     * قیمت هر واحد فیزیکی سفارش (معمولاً گرم یا عدد) برای محاسبه مبلغ
     */
    public function pricePerPhysicalUnit(
        string $price,
        string $pricingUnitId,
        string $physicalUnit
    ): string {
        if ($physicalUnit === 'count' || $pricingUnitId === 'count') {
            return $this->normalize($price);
        }

        // مقدار فیزیکی سفارش همیشه به گرم است؛ نرخ را به گرم تبدیل می‌کنیم
        return $this->convert($price, $pricingUnitId, 'gram', 6);
    }

    /**
     * نگاشت سازگاری با display_mode قدیمی برای کیمیا و سفارش‌های قبلی
     */
    public function toLegacyDisplayMode(string $targetUnitId): string
    {
        return $targetUnitId === 'gram' ? 'per_gram' : 'quotation';
    }

    public function fromLegacyDisplayMode(string $displayMode): string
    {
        return $displayMode === 'per_gram' ? 'gram' : 'mazane';
    }

    private function normalize(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return ($value === '' || $value === '-0') ? '0' : $value;
    }
}
