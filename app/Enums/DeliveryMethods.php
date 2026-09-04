<?php

namespace App\Enums;

class DeliveryMethods
{
    const IN_PERSON = 'in_person';
    const POST_OFFICE = 'post_office';
    const TIPAX = 'tipax';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
