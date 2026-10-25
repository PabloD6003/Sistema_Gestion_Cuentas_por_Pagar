<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 500,
        private readonly array $details = [],
        private readonly array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
