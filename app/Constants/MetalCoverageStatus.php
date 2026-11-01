<?php

namespace App\Constants;

class MetalCoverageStatus
{
    public const UNCOVERED = 'UNCOVERED';
    public const PARTIALLY_COVERED = 'PARTIALLY_COVERED';
    public const FULLY_COVERED = 'FULLY_COVERED';
    public const OVERDUE = 'OVERDUE';

    public static function all(): array
    {
        return [
            self::UNCOVERED,
            self::PARTIALLY_COVERED,
            self::FULLY_COVERED,
            self::OVERDUE,
        ];
    }

    public static function openStatuses(): array
    {
        return [
            self::UNCOVERED,
            self::PARTIALLY_COVERED,
            self::OVERDUE,
        ];
    }
}
