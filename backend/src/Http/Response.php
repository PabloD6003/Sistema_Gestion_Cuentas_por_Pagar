<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Utf8;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $data = Utf8::recursive($data);
        $body = json_encode($data, $flags);

        if ($body === false) {
            $body = json_encode($data, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if ($body === false) {
            $body = json_encode(['error' => 'No se pudo serializar la respuesta.']);
            $status = 500;
        }

        return new self($body, $status, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ], $headers));
    }

    public static function noContent(): self
    {
        return new self('', 204, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->status !== 204) {
            echo $this->body;
        }
    }
}
