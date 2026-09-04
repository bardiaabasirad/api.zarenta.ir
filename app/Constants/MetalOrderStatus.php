<?php

namespace App\Constants;

class MetalOrderStatus
{
    public const PENDING = 'pending';
    public const SUCCEED = 'succeed';
    public const REJECTED = 'rejected';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::SUCCEED,
            self::REJECTED,
        ];
    }
}
