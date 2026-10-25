<?php

declare(strict_types=1);

namespace App;

final class ApiException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        private int $statusCode = 0,
        private array $details = []
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
