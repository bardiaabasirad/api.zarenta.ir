<?php

namespace App\Constants;

class MetalTreasurySourceType
{
    public const TREASURY = 'TREASURY';
    public const PARTNER = 'PARTNER';
    public const USER_WALLET_SELL = 'USER_WALLET_SELL';
    public const PHYSICAL_PURCHASE = 'PHYSICAL_PURCHASE';
    public const TRADITIONAL_EXIT = 'TRADITIONAL_EXIT';
    public const MANUAL_ADJUSTMENT = 'MANUAL_ADJUSTMENT';
    public const MIGRATION = 'MIGRATION';

    public static function lotSources(): array
    {
        return [
            self::PARTNER,
            self::USER_WALLET_SELL,
            self::PHYSICAL_PURCHASE,
            self::MANUAL_ADJUSTMENT,
            self::MIGRATION,
        ];
    }

    public static function allocationSources(): array
    {
        return [
            self::TREASURY,
            self::PARTNER,
            self::USER_WALLET_SELL,
            self::PHYSICAL_PURCHASE,
            self::TRADITIONAL_EXIT,
        ];
    }
}
