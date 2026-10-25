<?php

declare(strict_types=1);

namespace App\Support;

use App\Config;

final class Format
{
    public static function money(mixed $value): string
    {
        $symbol = (string) Config::get('CURRENCY_SYMBOL', '₡');

        return $symbol . number_format((float) $value, 2, '.', ',');
    }

    public static function number(mixed $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, '.', ',');
    }

    public static function date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
    }

    public static function datetime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
    }

    public static function days(?int $days): string
    {
        if ($days === null) {
            return '—';
        }

        if ($days > 0) {
            return $days . ' días';
        }

        if ($days === 0) {
            return 'Vence hoy';
        }

        return 'En ' . abs($days) . ' días';
    }

    public static function percent(mixed $value): string
    {
        return number_format((float) $value, 2) . '%';
    }

    public static function badge(?string $state, ?string $label = null): string
    {
        $state = strtoupper(trim((string) $state));
        $label = $label ?? ($state === '' ? '—' : $state);

        $map = [
            'PENDIENTE' => 'is-warning',
            'PARCIAL' => 'is-info',
            'PAGADA' => 'is-success',
            'ANULADA' => 'is-danger',
            'ANULADO' => 'is-danger',
            'VENCIDA' => 'is-danger',
            'REGISTRADO' => 'is-light',
            'APROBADO' => 'is-success',
            'RECHAZADO' => 'is-danger',
            'CONCILIADO' => 'is-link',
            'GENERADO' => 'is-warning',
            'ENVIADO' => 'is-info',
            'PROCESADO' => 'is-success',
            'OK' => 'is-success',
            'ERROR' => 'is-danger',
            'ACTIVO' => 'is-success',
            'INACTIVO' => 'is-light',
        ];

        $class = $map[$state] ?? 'is-light';

        return '<span class="tag ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }
}
