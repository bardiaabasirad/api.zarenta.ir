<?php

namespace App\Enums;

class DirectoryStatus
{
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
