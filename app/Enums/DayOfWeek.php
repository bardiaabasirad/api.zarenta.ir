<?php

namespace App\Enums;

class DayOfWeek
{
    Const MONDAY = 'monday';
    Const TUESDAY = 'tuesday';
    Const WEDNESDAY = 'wednesday';
    Const THURSDAY = 'thursday';
    Const FRIDAY = 'friday';
    Const SATURDAY = 'saturday';
    Const SUNDAY = 'sunday';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
