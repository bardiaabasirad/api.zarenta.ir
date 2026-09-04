<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class HamtalaApiException extends Exception
{
    public function __construct(
        string $message = 'Hamtala API request failed.',
        private readonly int $statusCode = 500,
        private readonly array|string|null $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): array|string|null
    {
        return $this->responseBody;
    }

    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }
}
