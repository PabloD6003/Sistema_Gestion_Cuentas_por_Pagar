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
        $where = [];
        $params = [];

        if (($filters['id_cuenta_pagar'] ?? null) !== null) {
            $where[] = 'id_cuenta_pagar = ?';
            $params[] = (int) $filters['id_cuenta_pagar'];
        }

        $estado = $filters['estado'] ?? null;
        if (is_string($estado) && $estado !== '') {
            $where[] = 'estado = ?';
            $params[] = $estado;
        }

        $tipo = $filters['tipo'] ?? null;
        if (is_string($tipo) && $tipo !== '') {
            $where[] = 'tipo_movimiento = ?';
            $params[] = $tipo;
        }

        if (($filters['conciliado'] ?? null) !== null) {
            $where[] = 'conciliado = ?';
            $params[] = (int) $filters['conciliado'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(numero_factura LIKE ? OR razon_social LIKE ? OR referencia LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $totalRow = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM dbo.vw_movimientos' . $whereSql,
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT id_movimiento, id_cuenta_pagar, numero_factura, id_proveedor, razon_social,
                    numero_movimiento, fecha_movimiento, tipo_movimiento, monto, tipo_pago,
                    referencia, descripcion, estado, conciliado, id_lote, id_programacion,
                    id_movimiento_origen, fecha_registro
             FROM dbo.vw_movimientos' . $whereSql . '
             ORDER BY fecha_movimiento DESC, id_movimiento DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
            array_merge($params, [$offset, $perPage])
        );

        return [
            'items' => Cast::rows(
                $rows,
                ['id_movimiento', 'id_cuenta_pagar', 'id_proveedor', 'numero_movimiento', 'id_lote', 'id_programacion', 'id_movimiento_origen'],
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
        $rows = Database::fetchAll(
            'SELECT TOP (?) id_lote, id_banco, nombre_banco, fecha_envio, cantidad_pagos,
                    monto_total, estado, estado_nombre
             FROM dbo.vw_lotes
             ORDER BY id_lote DESC',
            [$limit]
        );

        return Cast::rows($rows, ['id_lote', 'id_banco', 'cantidad_pagos'], ['monto_total']);
    }

    public function changeLotState(int $id, string $estado, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_lote_cambiar_estado @id_lote = ?, @codigo_estado = ?, @id_usuario = ?;',
            [$id, $estado, $userId]
        );
    }
}
