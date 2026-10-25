<?php

declare(strict_types=1);

namespace App\Http;

final class RedirectException extends \RuntimeException
{
    public function __construct(private string $location, string $message = '')
    {
        parent::__construct($message);
    }

    public function location(): string
    {
        return $this->location;
    }
}
