<?php

declare(strict_types=1);

namespace App\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(string $message, private int $statusCode = 500)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
