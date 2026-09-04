<?php

namespace App\Enums;

class Gender
{
    const MALE = 'male';
    const FEMALE = 'female';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
