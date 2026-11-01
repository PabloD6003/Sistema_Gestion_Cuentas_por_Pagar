<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class ProveedorRepository
{
    /**
     * @param array{q?: string|null, estado?: int|null} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $estado = $filters['estado'] ?? null;

        // Devuelve dos conjuntos: el total y la página
        $sets = Database::callSets(
            'EXEC dbo.sp_proveedores_listar @buscar = ?, @estado = ?, @pagina = ?, @por_pagina = ?;',
            [$q === '' ? null : $q, $estado === null ? null : (int) $estado, $page, $perPage]
        );
        $total = (int) ($sets[0][0]['total'] ?? 0);
        $rows = $sets[1] ?? [];

        return [
            'items' => Cast::rows($rows, ['id_proveedor', 'dias_credito', 'evaluaciones'], ['calificacion_promedio'], ['estado']),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = Database::call('EXEC dbo.sp_proveedor_obtener @id_proveedor = ?;', [$id])[0] ?? null;

        return $row === null ? null : Cast::row($row, ['id_proveedor', 'dias_credito', 'evaluaciones'], ['calificacion_promedio'], ['estado']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $userId): int
    {
        $rows = Database::call(
            "SET NOCOUNT ON;
             DECLARE @id_proveedor INT;
             EXEC dbo.sp_proveedor_guardar
                 @id_proveedor = @id_proveedor OUTPUT,
                 @identificacion_tributaria = ?,
                 @razon_social = ?,
                 @nombre_comercial = ?,
                 @pais = ?,
                 @direccion = ?,
                 @telefono = ?,
                 @correo = ?,
                 @contacto_nombre = ?,
                 @dias_credito = ?,
                 @banco = ?,
                 @cuenta_bancaria = ?,
                 @estado = ?,
                 @codigo_erp = ?,
                 @id_usuario = ?;
             SELECT @id_proveedor AS id_proveedor;",
            [
                $data['identificacion_tributaria'],
                $data['razon_social'],
                $data['nombre_comercial'] ?? null,
                $data['pais'],
                $data['direccion'],
                $data['telefono'],
                $data['correo'],
                $data['contacto_nombre'] ?? null,
                (int) ($data['dias_credito'] ?? 30),
                $data['banco'],
                $data['cuenta_bancaria'],
                (int) ($data['estado'] ?? 1),
                $data['codigo_erp'] ?? null,
                $userId,
            ]
        );

        return (int) ($rows[0]['id_proveedor'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $userId): void
    {
        Database::execute(
            "SET NOCOUNT ON;
             DECLARE @id_proveedor INT = ?;
             EXEC dbo.sp_proveedor_guardar
                 @id_proveedor = @id_proveedor OUTPUT,
                 @identificacion_tributaria = ?,
                 @razon_social = ?,
                 @nombre_comercial = ?,
                 @pais = ?,
                 @direccion = ?,
                 @telefono = ?,
                 @correo = ?,
                 @contacto_nombre = ?,
                 @dias_credito = ?,
                 @banco = ?,
                 @cuenta_bancaria = ?,
                 @estado = ?,
                 @codigo_erp = ?,
                 @id_usuario = ?;",
            [
                $id,
                $data['identificacion_tributaria'],
                $data['razon_social'],
                $data['nombre_comercial'] ?? null,
                $data['pais'],
                $data['direccion'],
                $data['telefono'],
                $data['correo'],
                $data['contacto_nombre'] ?? null,
                (int) ($data['dias_credito'] ?? 30),
                $data['banco'],
                $data['cuenta_bancaria'],
                (int) ($data['estado'] ?? 1),
                $data['codigo_erp'] ?? null,
                $userId,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluations(int $id): array
    {
        $rows = Database::call('EXEC dbo.sp_proveedor_evaluaciones @id_proveedor = ?;', [$id]);

        return Cast::rows($rows, ['id_evaluacion', 'id_proveedor', 'calificacion']);
    }

    public function evaluate(int $id, int $calificacion, ?string $comentario, ?string $fecha, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_proveedor_evaluar @id_proveedor = ?, @calificacion = ?, @comentario = ?, @fecha = ?, @id_usuario = ?;',
            [$id, $calificacion, $comentario, $fecha, $userId]
        );
    }

    /**
     * Resumen de la relación comercial y movimientos (sp_reporte_historial_proveedor).
     *
     * @return array{resumen: array<string, mixed>|null, movimientos: array<int, array<string, mixed>>}
     */
    public function history(int $id): array
    {
        $sets = Database::callSets('EXEC dbo.sp_reporte_historial_proveedor @id_proveedor = ?;', [$id]);
        $resumen = $sets[0][0] ?? null;

        return [
            'resumen' => $resumen === null ? null : Cast::row(
                $resumen,
                ['dias_credito', 'cuentas_registradas'],
                ['monto_facturado', 'saldo_pendiente', 'total_pagado', 'descuentos_obtenidos', 'calificacion_promedio']
            ),
            'movimientos' => Cast::rows($sets[1] ?? [], ['id_movimiento', 'numero_movimiento'], ['monto'], ['conciliado']),
        ];
    }
}
