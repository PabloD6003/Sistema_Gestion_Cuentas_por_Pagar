<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class MovimientoRepository
{
    /**
     * @param array{id_cuenta_pagar?: int|null, estado?: string|null, tipo?: string|null, conciliado?: bool|null, q?: string|null} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $estado = $filters['estado'] ?? null;
        $tipo = $filters['tipo'] ?? null;
        $q = trim((string) ($filters['q'] ?? ''));
        $optionalInt = static fn (string $key): ?int => ($filters[$key] ?? null) === null ? null : (int) $filters[$key];

        // Devuelve dos conjuntos: el total y la página
        $sets = Database::callSets(
            'EXEC dbo.sp_movimientos_listar @id_cuenta_pagar = ?, @estado = ?, @tipo = ?, @id_banco = ?,
                  @conciliado = ?, @buscar = ?, @pagina = ?, @por_pagina = ?;',
            [
                $optionalInt('id_cuenta_pagar'),
                is_string($estado) && $estado !== '' ? $estado : null,
                is_string($tipo) && $tipo !== '' ? $tipo : null,
                $optionalInt('id_banco'),
                $optionalInt('conciliado'),
                $q === '' ? null : $q,
                $page,
                $perPage,
            ]
        );
        $total = (int) ($sets[0][0]['total'] ?? 0);
        $rows = $sets[1] ?? [];

        return [
            'items' => Cast::rows(
                $rows,
                ['id_movimiento', 'id_cuenta_pagar', 'id_proveedor', 'numero_movimiento', 'id_banco', 'id_lote', 'id_programacion', 'id_movimiento_origen'],
                ['monto'],
                ['conciliado']
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPayment(array $data, int $userId): array
    {
        $rows = Database::call(
            "SET NOCOUNT ON;
             DECLARE @id_movimiento INT, @monto_descuento DECIMAL(18,2);
             EXEC dbo.sp_pago_registrar
                 @id_cuenta_pagar = ?,
                 @monto = ?,
                 @id_tipo_pago = ?,
                 @fecha_pago = ?,
                 @referencia = ?,
                 @descripcion = ?,
                 @aplicar_descuento = ?,
                 @id_programacion = ?,
                 @id_usuario = ?,
                 @id_movimiento = @id_movimiento OUTPUT,
                 @monto_descuento = @monto_descuento OUTPUT;
             SELECT @id_movimiento AS id_movimiento, @monto_descuento AS monto_descuento;",
            [
                (int) $data['id_cuenta_pagar'],
                $data['monto'],
                (int) $data['id_tipo_pago'],
                $data['fecha_pago'] ?? null,
                $data['referencia'] ?? null,
                $data['descripcion'] ?? null,
                (int) ($data['aplicar_descuento'] ?? 1),
                $data['id_programacion'] ?? null,
                $userId,
            ]
        );

        return [
            'id_movimiento' => (int) ($rows[0]['id_movimiento'] ?? 0),
            'monto_descuento' => (float) ($rows[0]['monto_descuento'] ?? 0),
        ];
    }

    public function approve(int $id, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_movimiento_aprobar @id_movimiento = ?, @id_usuario = ?;',
            [$id, $userId]
        );
    }

    public function annulPayment(int $id, string $motivo, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_pago_anular @id_movimiento = ?, @motivo = ?, @id_usuario = ?;',
            [$id, $motivo, $userId]
        );
    }

    public function annulMovement(int $id, string $motivo, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_movimiento_anular @id_movimiento = ?, @motivo = ?, @id_usuario = ?;',
            [$id, $motivo, $userId]
        );
    }

    public function generateLot(int $idBanco, int $userId): int
    {
        $rows = Database::call(
            "SET NOCOUNT ON;
             DECLARE @id_lote INT;
             EXEC dbo.sp_lote_generar @id_banco = ?, @id_usuario = ?, @id_lote = @id_lote OUTPUT;
             SELECT @id_lote AS id_lote;",
            [$idBanco, $userId]
        );

        return (int) ($rows[0]['id_lote'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lots(int $limit = 100): array
    {
        $rows = Database::call('EXEC dbo.sp_lotes_listar @maximo = ?;', [$limit]);

        return Cast::rows($rows, ['id_lote', 'id_banco', 'cantidad_pagos'], ['monto_total']);
    }

    /**
     * Concilia las líneas del extracto bancario con los pagos aprobados del banco.
     *
     * @param array<int, array{fecha: string, referencia: string, monto: float}> $lineas
     * @return array<int, array<string, mixed>>
     */
    public function reconcile(int $idBanco, array $lineas, int $toleranciaDias, int $userId): array
    {
        $extracto = json_encode($lineas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $rows = Database::call(
            'EXEC dbo.sp_conciliar_extracto @id_banco = ?, @extracto = ?, @tolerancia_dias = ?, @id_usuario = ?;',
            [$idBanco, $extracto === false ? '[]' : $extracto, $toleranciaDias, $userId]
        );

        return Cast::rows($rows, ['linea', 'id_movimiento'], ['monto']);
    }

    public function changeLotState(int $id, string $estado, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_lote_cambiar_estado @id_lote = ?, @codigo_estado = ?, @id_usuario = ?;',
            [$id, $estado, $userId]
        );
    }
}
