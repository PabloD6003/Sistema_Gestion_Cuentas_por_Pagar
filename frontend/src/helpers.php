<?php

declare(strict_types=1);

use App\Security\Csrf;
use App\Support\Format;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $value): string
{
    return Format::money($value);
}

function fmt_date(?string $value): string
{
    return Format::date($value);
}

function fmt_datetime(?string $value): string
{
    return Format::datetime($value);
}

function fmt_days(?int $days): string
{
    return Format::days($days);
}

function fmt_month(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) === 1) {
        return $matches[2] . '/' . $matches[1];
    }

    return $value;
}

function state_badge(?string $state, ?string $label = null): string
{
    return Format::badge($state, $label);
}

function csrf_field(): string
{
    return Csrf::field();
}

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return array<int, array{type: string, message: string}>
 */
function take_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($flashes) ? $flashes : [];
}

/**
 * Calificación de 1 a 5 como estrellas, con el valor numérico para lectores de pantalla.
 */
function rating_stars(mixed $value): string
{
    if ($value === null || $value === '') {
        return '<span class="app-stars is-empty">Sin evaluar</span>';
    }

    $rating = max(0.0, min(5.0, (float) $value));
    $full = (int) round($rating);
    $label = number_format($rating, $rating === floor($rating) ? 0 : 1) . ' de 5';

    return '<span class="app-stars" title="' . e($label) . '" aria-label="' . e($label) . '">'
        . str_repeat('★', $full) . '<span class="app-stars-off">' . str_repeat('★', 5 - $full) . '</span>'
        . '</span>';
}

function old(array $old, string $key, mixed $default = ''): mixed
{
    return $old[$key] ?? $default;
}

function is_active_path(string $path, string $current): bool
{
    if ($path === '/dashboard') {
        return $current === '/dashboard';
    }

    return $current === $path || str_starts_with($current, $path . '/');
}
