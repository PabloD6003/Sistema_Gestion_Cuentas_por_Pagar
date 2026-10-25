<?php

declare(strict_types=1);

use App\Http\HttpException;
use App\Support\Validator;

require __DIR__ . '/../autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
}

check(Validator::date('2026-10-24', 'fecha') === '2026-10-24', 'Fecha válida');
check(Validator::decimal('1017.50', 'monto') === 1017.50, 'Decimal de dos posiciones');
check(Validator::decimal('0.1250', 'cantidad', 0.0001, 4) === 0.125, 'Decimal de cuatro posiciones');
check(Validator::int('5', 'id', 1) === 5, 'Entero válido');
check(Validator::bool('true', false) === true, 'Booleano verdadero');

try {
    Validator::date('2026-02-30', 'fecha');
    check(false, 'Una fecha inválida debe fallar');
} catch (HttpException $exception) {
    check($exception->getStatusCode() === 422, 'Fecha inválida debe ser 422');
}

try {
    Validator::required(['a' => 1], ['a', 'b']);
    check(false, 'Un campo faltante debe fallar');
} catch (HttpException $exception) {
    check($exception->getStatusCode() === 422, 'Campo faltante debe ser 422');
    check(($exception->getDetails()['missing'] ?? []) === ['b'], 'Debe indicar el campo faltante');
}

fwrite(STDOUT, "validator_test: OK\n");
