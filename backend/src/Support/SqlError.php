<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

final class SqlError
{
    public static function message(PDOException $exception): string
    {
        $message = $exception->getMessage();

        if (preg_match('/\[SQL Server\](.*?)(?:\[|$)/s', $message, $match) === 1) {
            $message = trim($match[1]);
        }

        $message = preg_replace('/^\[[^\]]+\]\s*/', '', $message) ?? $message;
        $message = preg_replace('/\s*\[[^\]]+\]\s*$/', '', $message) ?? $message;

        return trim($message) !== '' ? trim($message) : 'Error de base de datos.';
    }

    public static function status(PDOException $exception): int
    {
        $state = (string) $exception->getCode();

        return $state === '23000' ? 409 : 400;
    }
}
