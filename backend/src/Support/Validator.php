<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\HttpException;
use DateTimeImmutable;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fields
     */
    public static function required(array $data, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new HttpException('Faltan campos obligatorios.', 422, ['missing' => $missing]);
        }
    }

    public static function int(mixed $value, string $field, ?int $min = null): int
    {
        if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
            throw self::invalid($field, 'Debe ser un número entero.');
        }

        $int = (int) $value;
        if ($min !== null && $int < $min) {
            throw self::invalid($field, "Debe ser mayor o igual a {$min}.");
        }

        return $int;
    }

    public static function decimal(mixed $value, string $field, float $min = 0.01, int $scale = 2): float
    {
        if (!is_numeric($value)) {
            throw self::invalid($field, 'Debe ser un número.');
        }

        $decimal = (float) $value;
        if ($decimal < $min) {
            throw self::invalid($field, "Debe ser mayor o igual a {$min}.");
        }

        return round($decimal, $scale);
    }

    public static function date(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw self::invalid($field, 'Debe ser una fecha YYYY-MM-DD.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw self::invalid($field, 'Debe ser una fecha válida YYYY-MM-DD.');
        }

        return $date->format('Y-m-d');
    }

    public static function string(mixed $value, string $field, int $max = 255, bool $required = true): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw self::invalid($field, 'Es obligatorio.');
            }

            return null;
        }

        if (!is_scalar($value)) {
            throw self::invalid($field, 'Debe ser texto.');
        }

        $text = trim((string) $value);
        if ($text === '' && $required) {
            throw self::invalid($field, 'Es obligatorio.');
        }

        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length > $max) {
            throw self::invalid($field, "No puede superar {$max} caracteres.");
        }

        return $text;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool ?? $default;
    }

    /**
     * @return array<int, mixed>
     */
    public static function array(mixed $value, string $field): array
    {
        if (!is_array($value) || $value === []) {
            throw self::invalid($field, 'Debe ser un arreglo con al menos un elemento.');
        }

        return $value;
    }

    public static function invalid(string $field, string $message): HttpException
    {
        return new HttpException('Datos inválidos.', 422, [
            'field' => $field,
            'message' => $message,
        ]);
    }
}
