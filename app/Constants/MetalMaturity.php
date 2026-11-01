<?php

namespace App\Constants;

class MetalMaturity
{
    public const T0 = 'T0';
    public const T1 = 'T1';
    public const T2 = 'T2';

    public static function all(): array
    {
        return [self::T0, self::T1, self::T2];
    }

    public static function fromWorkingDays(int $days): string
    {
        return match (true) {
            $days <= 0 => self::T0,
            $days === 1 => self::T1,
            default => self::T2,
        };
    }

    /**
     * سررسیدهای مجاز منبع برای پوشش یک سفارش با سررسید مشخص.
     * استفاده از سررسید دیرتر برای سفارش زودتر ممنوع است.
     *
     * @return list<string>
     */
    public static function allowedSourceMaturities(string $orderMaturity): array
    {
        return match ($orderMaturity) {
            self::T0 => [self::T0],
            self::T1 => [self::T1, self::T0],
            self::T2 => [self::T2, self::T1, self::T0],
            default => [self::T0],
        };
    }

    /**
     * اولویت مصرف: اول همان سررسید سفارش، سپس سررسیدهای زودتر.
     *
     * @return list<string>
     */
    public static function coveragePriority(string $orderMaturity): array
    {
        return self::allowedSourceMaturities($orderMaturity);
    }
}
