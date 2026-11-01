<?php

namespace App\Constants;

class MetalTreasuryMovementType
{
    public const IN = 'IN';
    public const RESERVE = 'RESERVE';
    public const ALLOCATE = 'ALLOCATE';
    public const RELEASE = 'RELEASE';
    public const ADJUST = 'ADJUST';
    public const EXTERNAL_OUT = 'EXTERNAL_OUT';
    public const PHYSICAL_DELIVERY_OUT = 'PHYSICAL_DELIVERY_OUT';

    public static function all(): array
    {
        return [
            self::IN,
            self::RESERVE,
            self::ALLOCATE,
            self::RELEASE,
            self::ADJUST,
            self::EXTERNAL_OUT,
            self::PHYSICAL_DELIVERY_OUT,
        ];
    }

    /** جهت اثر روی remaining_qty: منفی = کاهش موجودی آزاد */
    public static function signedDelta(string $type, string $qty, int $scale): string
    {
        return match ($type) {
            self::IN, self::RELEASE => bcadd($qty, '0', $scale),
            self::RESERVE, self::ALLOCATE, self::EXTERNAL_OUT, self::PHYSICAL_DELIVERY_OUT => bcsub('0', $qty, $scale),
            self::ADJUST => bcadd($qty, '0', $scale), // qty می‌تواند منفی باشد در caller
            default => throw new \InvalidArgumentException("Unknown movement type: {$type}"),
        };
    }
}
