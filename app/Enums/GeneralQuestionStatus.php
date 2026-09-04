<?php


namespace App\Enums;


class GeneralQuestionStatus
{
    const NEW_QUESTION = 'new';
    const REJECTED = 'rejected';
    const CONFIRMED = 'confirmed';
    const CLOSED = 'closed';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
