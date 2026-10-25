<?php

declare(strict_types=1);

namespace App;

final class ApiClient
{
    private string $baseUrl;
    private string $cookieJar;

    public function __construct(string $baseUrl, string $cookieJar)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = $cookieJar;

        $directory = dirname($cookieJar);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    public static function forSession(): self
    {
        if (empty($_SESSION['api_jar_id'])) {
            $_SESSION['api_jar_id'] = bin2hex(random_bytes(16));
        }

        $baseUrl = (string) Config::get('API_BASE_URL', 'http://localhost:8000');
        $cookieJar = APP_BASE_PATH . '/storage/cookies/' . $_SESSION['api_jar_id'] . '.txt';

        return new self($baseUrl, $cookieJar);
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $username, string $password): array
    {
        $this->deleteCookieJar();
        unset($_SESSION['api_csrf']);

        $data = $this->request('POST', '/api/auth/login', [
            'nombre_usuario' => $username,
            'contrasena' => $password,
        ], false);

        if (isset($data['csrf_token']) && is_string($data['csrf_token'])) {
            $_SESSION['api_csrf'] = $data['csrf_token'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return $this->request('GET', '/api/auth/me');
    }

    public function logout(): void
    {
        try {
            $this->request('POST', '/api/auth/logout', []);
        } catch (ApiException) {
            // La sesión del API pudo haber expirado; se limpia igualmente.
        }

        $this->deleteCookieJar();
        unset($_SESSION['api_csrf']);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, bool $sendCsrf = true): array
    {
        if (!function_exists('curl_init')) {
            throw new ApiException('La extensión cURL de PHP no está habilitada en el servidor de la interfaz.');
        }

        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        if ($ch === false) {
            throw new ApiException('No se pudo iniciar la conexión con la API.');
        }

        $headers = ['Accept: application/json'];
        $payload = null;

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new ApiException('No se pudo preparar la solicitud a la API.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        if ($sendCsrf && !empty($_SESSION['api_csrf'])) {
            $headers[] = 'X-CSRF-Token: ' . $_SESSION['api_csrf'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false || $errno !== 0) {
            throw new ApiException('No se pudo conectar con la API: ' . $error);
        }

        $decoded = [];
        if ($raw !== '') {
            $json = json_decode($raw, true);
            $decoded = is_array($json) ? $json : ['raw' => $raw];
        }

        if ($status >= 400) {
            $message = is_string($decoded['error'] ?? null)
                ? $decoded['error']
                : 'La API respondió con un error (' . $status . ').';
            $details = is_array($decoded['details'] ?? null) ? $decoded['details'] : [];
            throw new ApiException($message, $status, $details);
        }

        if (isset($decoded['csrf_token']) && is_string($decoded['csrf_token'])) {
            $_SESSION['api_csrf'] = $decoded['csrf_token'];
        }

        return $decoded;
    }

    public function deleteCookieJar(): void
    {
        if (is_file($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }
}
