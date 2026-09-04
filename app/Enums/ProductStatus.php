<?php

namespace App\Enums;

class ProductStatus
{
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
