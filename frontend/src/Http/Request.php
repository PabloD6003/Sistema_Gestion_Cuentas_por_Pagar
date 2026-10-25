<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $headers,
        private ?array $json
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim((string) ($uri ?: '/'), '/');
        if ($path === '') {
            $path = '/';
        }

        $headers = [];
        $rawHeaders = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($rawHeaders as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $json = null;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return new self($method, $path, $_GET, $_POST, $headers, $json);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $headers = [],
        ?array $json = null
    ): self {
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        return new self(strtoupper($method), $path, $query, $post, array_change_key_case($headers, CASE_LOWER), $json);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function postAll(): array
    {
        return $this->post;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isHtmx(): bool
    {
        return strtolower((string) $this->header('HX-Request', '')) === 'true';
    }
}
