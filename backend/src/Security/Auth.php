<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;

final class Auth
{
    /**
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user'] = [
            'id_usuario' => (int) $user['id_usuario'],
            'nombre_usuario' => (string) $user['nombre_usuario'],
            'nombre_completo' => (string) $user['nombre_completo'],
            'correo' => (string) $user['correo'],
            'id_rol' => (int) $user['id_rol'],
            'rol' => (string) $user['rol'],
            'puede_aprobar_pagos' => (bool) $user['puede_aprobar_pagos'],
        ];
        $_SESSION['login_at'] = time();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id_usuario']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user']['id_usuario'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireLogin(): array
    {
        if (!self::check()) {
            throw new HttpException('Debe iniciar sesión.', 401);
        }

        return $_SESSION['user'];
    }

    public static function canApprove(): bool
    {
        return (bool) ($_SESSION['user']['puede_aprobar_pagos'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireApprove(): array
    {
        $user = self::requireLogin();
        if (!self::canApprove()) {
            throw new HttpException('Su rol no permite aprobar pagos.', 403);
        }

        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
