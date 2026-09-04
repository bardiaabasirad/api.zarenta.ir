<?php


namespace App\Enums;


class AdvisorFlowStatus
{
    const REGISTER = 'register';
    const ANSWER_TO_GENERAL_QUESTION = 'answer_to_general_question';
    const ANSWER_TO_COUNSELING = 'answer_to_counseling';
    const CHANGE_PROFILE = 'change_profile';
    const COMPLETE_PROFILE = 'complete_profile';
    const UPDATE = 'update';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
