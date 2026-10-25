<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use App\Support\Format;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FALLO: ' . $message . PHP_EOL);
        exit(1);
    }
}

check(Format::date('2026-10-24') === '24/10/2026', 'La fecha debe formatearse como dd/mm/aaaa.');
check(Format::date(null) === '—', 'Una fecha nula debe mostrar un guion.');
check(Format::days(-3) === 'En 3 días', 'Los días negativos deben indicar cuánto falta.');
check(Format::days(0) === 'Vence hoy', 'Cero días debe indicar vencimiento hoy.');
check(Format::days(5) === '5 días', 'Los días positivos deben indicar atraso.');
check(Format::percent(87.5) === '87.50%', 'El porcentaje debe tener dos decimales.');
check(str_contains(Format::badge('PAGADA'), 'is-success'), 'PAGADA debe usar la etiqueta de éxito.');
check(str_contains(Format::badge('ANULADA'), 'is-danger'), 'ANULADA debe usar la etiqueta de peligro.');

$money = Format::money(1130);
check(str_contains($money, '1,130.00'), 'El monto debe incluir separador de miles y dos decimales.');
check(str_contains($money, '₡'), 'El monto debe incluir el símbolo de colón.');

echo "format_test: OK\n";
