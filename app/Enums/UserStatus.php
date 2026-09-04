<?php

namespace App\Enums;

class UserStatus
{
    const INITIAL = 'initial';
    const INCOMPLETE = 'incomplete';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
