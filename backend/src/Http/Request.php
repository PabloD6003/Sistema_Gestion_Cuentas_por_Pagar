<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly string $body,
        private readonly ?array $json,
        private readonly bool $jsonValid
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = self::normalizePath($path);

        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower((string) $name)] = (string) $value;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$name] = (string) $value;
                }
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $body = (string) file_get_contents('php://input');
        $json = null;
        $jsonValid = true;
        $contentType = strtolower($headers['content-type'] ?? '');
        $looksLikeJson = $body !== ''
            && (str_contains($contentType, 'json') || preg_match('/^\s*[\[\{]/', $body) === 1);

        if ($looksLikeJson) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $json = $decoded;
            } else {
                $jsonValid = false;
            }
        }

        return new self(
            $method,
            $path,
            $_GET,
            $headers,
            $body,
            $json,
            $jsonValid
        );
    }

    /**
     * Constructor para pruebas unitarias.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $path, ?array $json = null, array $headers = []): self
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $parsedPath = is_string($parsedPath) ? $parsedPath : '/';
        $query = [];
        $queryString = parse_url($path, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }

        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }

        $body = $json === null ? '' : (string) json_encode($json);

        return new self(
            strtoupper($method),
            self::normalizePath($parsedPath),
            $query,
            $normalizedHeaders,
            $body,
            $json,
            true
        );
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (!$this->jsonValid) {
            throw new HttpException('El cuerpo JSON no es válido.', 400);
        }

        return $this->json ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->json !== null && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->json ?? $_POST;
    }

    public function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $this->header('user-agent');
    }
}
