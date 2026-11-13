<?php

namespace App\Constants;

class MetalItemGroupIcon
{
    public const MELTED_GOLD = 'melted_gold';
    public const BANK_COINS = 'bank_coins';
    public const MELTED_SILVER = 'melted_silver';
    public const SILVER_BAR = 'silver_bar';
    public const MELTED_COPPER = 'melted_copper';

    public static function all(): array
    {
        return [
            self::MELTED_GOLD,
            self::BANK_COINS,
            self::MELTED_SILVER,
            self::SILVER_BAR,
            self::MELTED_COPPER,
        ];
    }

    public static function labels(): array
    {
        return [
            self::MELTED_GOLD => 'طلای آبشده',
            self::BANK_COINS => 'سکه‌های بانکی',
            self::MELTED_SILVER => 'نقره آبشده',
            self::SILVER_BAR => 'شمش نقره',
            self::MELTED_COPPER => 'مس آبشده',
        ];
    }
}
