<?php

namespace App\Enums;

class BankAccountStatus
{
    const CHECKING = 'checking';
    const CONFIRMED = 'confirmed';
    const REJECTED = 'rejected';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
