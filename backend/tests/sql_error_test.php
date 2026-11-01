<?php

declare(strict_types=1);

use App\Support\SqlError;

require __DIR__ . '/../autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
}

function sqlException(string $state, int $number, string $text): PDOException
{
    $exception = new PDOException(
        "SQLSTATE[{$state}]: Error: {$number} [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]{$text}"
        . " (SQLExecute[{$number}] at ext\\pdo_odbc\\odbc_stmt.c:323)"
    );
    $exception->errorInfo = [$state, $number, $text];

    return $exception;
}

$negocio = sqlException('42000', 50044, 'El monto supera el saldo disponible de la cuenta.');
check(SqlError::status($negocio) === 422, 'Regla de negocio debe ser 422');
check(SqlError::message($negocio) === 'El monto supera el saldo disponible de la cuenta.', 'Mensaje sin texto técnico');
check(
    SqlError::publicMessage($negocio, false) === 'El monto supera el saldo disponible de la cuenta.',
    'Regla de negocio visible aunque APP_DEBUG esté apagado'
);

$permiso = sqlException('42000', 50060, 'El rol del usuario no permite aprobar movimientos.');
check(SqlError::status($permiso) === 403, 'Regla de permisos debe ser 403');

$inexistente = sqlException('42000', 50010, 'El proveedor no existe.');
check(SqlError::status($inexistente) === 404, 'Registro inexistente debe ser 404');

$duplicado = sqlException('23000', 2627, "Violation of UNIQUE KEY constraint 'UQ_CP01_identificacion'.");
check(SqlError::status($duplicado) === 409, 'Clave duplicada debe ser 409');
check(SqlError::publicMessage($duplicado, false) === 'Ya existe un registro con esos datos.', 'Duplicado sin detalle técnico');

$check = sqlException('23000', 547, 'The INSERT statement conflicted with the CHECK constraint "CK_CP01_dias_credito".');
check(SqlError::status($check) === 422, 'Violación de CHECK debe ser 422');

$tecnico = sqlException('42S02', 208, "Invalid object name 'dbo.x'.");
check(SqlError::status($tecnico) === 500, 'Error técnico debe ser 500');
check(
    SqlError::publicMessage($tecnico, false) === 'No se pudo completar la operación en la base de datos.',
    'Error técnico oculto sin APP_DEBUG'
);

fwrite(STDOUT, "sql_error_test: OK\n");
