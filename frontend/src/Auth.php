<?php

declare(strict_types=1);

namespace App;

use App\Http\RedirectException;
use App\Security\Csrf;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function login(array $data): void
    {
        $user = $data['user'] ?? null;
        if (!is_array($user)) {
            throw new \RuntimeException('La API no devolvió el usuario autenticado.');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;

        if (isset($data['csrf_token']) && is_string($data['csrf_token'])) {
            $_SESSION['api_csrf'] = $data['csrf_token'];
        }

        Csrf::rotate();
    }

    public static function logout(): void
    {
        if (isset($_SESSION['api_jar_id']) && is_string($_SESSION['api_jar_id'])) {
            $jar = APP_BASE_PATH . '/storage/cookies/' . $_SESSION['api_jar_id'] . '.txt';
            if (is_file($jar)) {
                @unlink($jar);
            }
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
            session_destroy();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireLogin(): array
    {
        if (!self::check()) {
            throw new RedirectException('/login', 'Debe iniciar sesión.');
        }

        return $_SESSION['user'];
    }

    public static function canApprove(): bool
    {
        $user = self::user();

        return (bool) ($user['puede_aprobar_pagos'] ?? false);
    }
}
