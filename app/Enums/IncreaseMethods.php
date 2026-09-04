<?php

namespace App\Enums;

class IncreaseMethods
{
    const CASH = 'cash';
    const PAYA = 'paya';
    const CARD = 'card';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
