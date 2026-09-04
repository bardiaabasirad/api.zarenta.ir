<?php


namespace App\Enums;


class GeneralQuestionFlowStatus
{
    const CREATE_QUESTION = 'create_question';
    const CONFIRM_QUESTION = 'confirm_question';
    const REJECT_QUESTION = 'reject_question';
    const EDIT_QUESTION = 'edit_question';
    const SET_CATEGORY = 'set_category';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
