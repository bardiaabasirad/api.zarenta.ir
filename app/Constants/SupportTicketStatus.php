<?php

namespace App\Constants;

class SupportTicketStatus
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';
    public const AUTO_CLOSED = 'auto_closed';

    public static function all(): array
    {
        return [
            self::OPEN,
            self::ANSWERED,
            self::CLOSED,
            self::AUTO_CLOSED,
        ];
    }

    public static function openStatuses(): array
    {
        return [self::OPEN, self::ANSWERED];
    }

    public static function closedStatuses(): array
    {
        return [self::CLOSED, self::AUTO_CLOSED];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::OPEN => 'باز',
            self::ANSWERED => 'پاسخ داده‌شده',
            self::CLOSED => 'بسته‌شده',
            self::AUTO_CLOSED => 'بسته خودکار',
            default => $status,
        };
    }
}
