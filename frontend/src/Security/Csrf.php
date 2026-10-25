<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;
use App\Http\Request;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(Request $request): void
    {
        $token = $request->post('_csrf');
        if (!is_string($token) || $token === '') {
            $token = $request->header('X-CSRF-Token', '');
        }

        if (!is_string($token) || $token === '' || !hash_equals(self::token(), $token)) {
            throw new HttpException('Token CSRF inválido o ausente.', 419);
        }
    }
}
