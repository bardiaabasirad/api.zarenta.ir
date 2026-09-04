<?php

namespace App\Enums;

class Option
{
    const INCREASE_INVENTORY_COUNT = 'increase_inventory_count';
    const DECREASE_INVENTORY_COUNT = 'decrease_inventory_count';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
