<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

final class SqlError
{
    /** Errores de negocio lanzados con THROW 50000–59999 en los procedimientos. */
    private const BUSINESS_MIN = 50000;
    private const BUSINESS_MAX = 59999;

    /** Reglas de permisos de los procedimientos (rol sin autorización). */
    private const FORBIDDEN = [50030, 50060, 50070, 50080, 50090, 50130];

    /** Reglas que indican que el registro pedido no existe. */
    private const NOT_FOUND = [50010, 50032, 50040, 50061, 50071, 50082, 50091, 50096];

    /** Errores de conversión o desbordamiento de datos enviados por el cliente. */
    private const DATA_ERRORS = [220, 232, 245, 8114, 8115];

    public static function message(PDOException $exception): string
    {
        $message = $exception->getMessage();

        if (preg_match('/\[SQL Server\](.*?)(?:\[|$)/s', $message, $match) === 1) {
            $message = trim($match[1]);
        }

        $message = preg_replace('/^\[[^\]]+\]\s*/', '', $message) ?? $message;
        $message = preg_replace('/\s*\[[^\]]+\]\s*$/', '', $message) ?? $message;
        // pdo_odbc agrega al final "(SQLExecute[n] at ext\pdo_odbc\...)"
        $message = preg_replace('/\s*\(SQL\w+(?:\[\d+\] at [^)]*\))?\s*$/', '', $message) ?? $message;

        return trim($message) !== '' ? trim($message) : 'Error de base de datos.';
    }

    /**
     * Número de error nativo de SQL Server (0 si el error vino del driver).
     */
    public static function number(PDOException $exception): int
    {
        return (int) ($exception->errorInfo[1] ?? 0);
    }

    public static function isBusinessRule(PDOException $exception): bool
    {
        $number = self::number($exception);

        return $number >= self::BUSINESS_MIN && $number <= self::BUSINESS_MAX;
    }

    public static function status(PDOException $exception): int
    {
        $number = self::number($exception);
        $state = (string) $exception->getCode();

        return match (true) {
            in_array($number, self::FORBIDDEN, true) => 403,
            in_array($number, self::NOT_FOUND, true) => 404,
            self::isBusinessRule($exception) => 422,
            $number === 2627 || $number === 2601 => 409,
            $number === 547 => 422,
            in_array($number, self::DATA_ERRORS, true),
            in_array($state, ['22001', '22003', '22007', '22008', '22018'], true) => 422,
            default => 500,
        };
    }

    /**
     * Mensaje para el usuario. Las reglas de negocio siempre se muestran; los errores
     * técnicos solo se detallan con APP_DEBUG activo.
     */
    public static function publicMessage(PDOException $exception, bool $debug): string
    {
        if (self::isBusinessRule($exception)) {
            return self::message($exception);
        }

        $number = self::number($exception);
        $generic = match (true) {
            $number === 2627 || $number === 2601 => 'Ya existe un registro con esos datos.',
            $number === 547 => 'Los datos no cumplen las reglas de la base de datos.',
            self::status($exception) === 422 => 'Uno de los valores enviados no es válido o está fuera de rango.',
            default => 'No se pudo completar la operación en la base de datos.',
        };

        return $debug ? $generic . ' Detalle: ' . self::message($exception) : $generic;
    }
}
