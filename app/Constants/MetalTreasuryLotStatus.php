<?php

namespace App\Constants;

class MetalTreasuryLotStatus
{
    public const OPEN = 'OPEN';
    public const DEPLETED = 'DEPLETED';
    public const CLOSED_EXTERNAL = 'CLOSED_EXTERNAL';

    public static function all(): array
    {
        return [
            self::OPEN,
            self::DEPLETED,
            self::CLOSED_EXTERNAL,
        ];
    }
}
