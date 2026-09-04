<?php

namespace App\Enums;

class SectionStatus
{
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const LOCK = 'lock';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
