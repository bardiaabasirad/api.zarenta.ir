<?php

namespace App\Constants;

class MetalCoverageAttemptStatus
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const TIMEOUT = 'timeout';
    public const INVALID = 'invalid';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::SENT,
            self::SUCCEEDED,
            self::FAILED,
            self::TIMEOUT,
            self::INVALID,
        ];
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, [
            self::SUCCEEDED,
            self::FAILED,
            self::TIMEOUT,
            self::INVALID,
        ], true);
    }
}
