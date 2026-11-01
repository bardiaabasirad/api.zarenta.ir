<?php

namespace App\Constants;

class MetalPhysicalDeliverySource
{
    /** خروج از Lot خرید حضوری ثبت‌شده در خزانه */
    public const TREASURY_PHYSICAL = 'TREASURY_PHYSICAL';

    /** موجودی ثبت‌نشده مغازه — فقط audit، بدون کسر خزانه/بدهی */
    public const SHOP_UNREGISTERED = 'SHOP_UNREGISTERED';

    public static function all(): array
    {
        return [
            self::TREASURY_PHYSICAL,
            self::SHOP_UNREGISTERED,
        ];
    }
}
