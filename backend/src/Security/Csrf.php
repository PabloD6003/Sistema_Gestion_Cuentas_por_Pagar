<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\HttpException;
use App\Http\Request;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || $_SESSION['_csrf'] === '') {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function rotate(): string
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        return $_SESSION['_csrf'];
    }

    public static function validate(Request $request): void
    {
        $token = $request->header('x-csrf-token');
        $expected = self::token();

        if (!is_string($token) || $token === '' || !hash_equals($expected, $token)) {
            throw new HttpException('Token CSRF inválido o ausente.', 419);
        }
    }
}
