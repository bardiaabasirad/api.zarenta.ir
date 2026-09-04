<?php


namespace App\Enums;


class PostFlowStatus
{
    const CREATE_POST = 'create_post';
    const CREATE_COMMENT = 'create_comment';
    const DELETE_COMMENT = 'delete_comment';
    const EDIT_COMMENT = 'edit_comment';

    public static function classConstants()
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
