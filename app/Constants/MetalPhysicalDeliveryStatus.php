<?php

namespace App\Constants;

class MetalPhysicalDeliveryStatus
{
    public const PENDING = 'PENDING';
    public const READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    public const DELIVERED = 'DELIVERED';
    public const CANCELLED = 'CANCELLED';
    public const REJECTED = 'REJECTED';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::READY_FOR_PICKUP,
            self::DELIVERED,
            self::CANCELLED,
            self::REJECTED,
        ];
    }

    public static function cancellable(): array
    {
        return [
            self::PENDING,
            self::READY_FOR_PICKUP,
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        $map = [
            self::PENDING => [
                self::READY_FOR_PICKUP,
                self::DELIVERED,
                self::CANCELLED,
                self::REJECTED,
            ],
            self::READY_FOR_PICKUP => [
                self::DELIVERED,
                self::CANCELLED,
                self::REJECTED,
            ],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }
}
