<?php

namespace App\Constants;

class MetalSettlementCaseStatus
{
    public const OPEN = 'open';
    public const FULFILLED = 'fulfilled';
    public const DEFAULTED = 'defaulted';

    public static function all(): array
    {
        return [
            self::OPEN,
            self::FULFILLED,
            self::DEFAULTED,
        ];
    }
}
