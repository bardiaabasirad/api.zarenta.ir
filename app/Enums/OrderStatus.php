<?php

namespace App\Enums;

class OrderStatus
{
    const WAIT_PAYMENT = 'wait_payment';
    const RESERVED = 'reserved';
    const PAID = 'paid';
    const COLLECTING = 'collecting';
    const COLLECTED = 'collected';
    const DELIVERED = 'delivered';
    const CANCELED = 'canceled';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
