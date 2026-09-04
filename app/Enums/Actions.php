<?php

namespace App\Enums;

class Actions
{
    const CREATE = 'create';
    const UPDATE = 'update';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
