<?php

namespace App\Enums;

class UserRole
{
    const USER = 'user';
    const ADVISOR = 'advisor';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
