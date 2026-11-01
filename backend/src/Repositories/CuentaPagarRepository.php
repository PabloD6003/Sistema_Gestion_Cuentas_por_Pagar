<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class CuentaPagarRepository
{
    /**
     * @param array{q?: string|null, estado?: string|null, id_proveedor?: int|null, vencidas?: bool} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $estado = $filters['estado'] ?? null;
        $idProveedor = $filters['id_proveedor'] ?? null;

        // Devuelve dos conjuntos: el total y la página
        $sets = Database::callSets(
            'EXEC dbo.sp_cuentas_listar @buscar = ?, @estado = ?, @id_proveedor = ?, @solo_vencidas = ?,
                  @pagina = ?, @por_pagina = ?;',
            [
                $q === '' ? null : $q,
                is_string($estado) && $estado !== '' ? $estado : null,
                $idProveedor === null ? null : (int) $idProveedor,
                ($filters['vencidas'] ?? false) === true ? 1 : 0,
                $page,
                $perPage,
            ]
        );
        $total = (int) ($sets[0][0]['total'] ?? 0);
        $rows = $sets[1] ?? [];

        return [
            'items' => Cast::rows(
                $rows,
                ['id_cuenta_pagar', 'id_proveedor', 'dias_vencido'],
                ['monto_total', 'saldo_pendiente']
            ),
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
        // Devuelve dos conjuntos: el encabezado (con la marca editable) y las líneas
        $sets = Database::callSets('EXEC dbo.sp_cuenta_pagar_obtener @id_cuenta_pagar = ?;', [$id]);
        $row = $sets[0][0] ?? null;

        if ($row === null) {
            return null;
        }

        $detalle = $sets[1] ?? [];

        $row = Cast::row(
            $row,
            ['id_cuenta_pagar', 'id_proveedor', 'dias_credito', 'dias_vencido'],
            ['pct_descuento_pronto_pago', 'subtotal', 'monto_impuesto', 'monto_total', 'saldo_pendiente'],
            ['editable']
        );
        $row['detalle'] = Cast::rows(
            $detalle,
            ['id_detalle', 'id_cuenta_pagar', 'numero_linea'],
            ['cantidad', 'precio_unitario', 'pct_impuesto', 'monto_impuesto', 'total_linea']
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $userId): int
    {
        $detalle = json_encode($data['detalle'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($detalle === false) {
            $detalle = '[]';
        }

        $rows = Database::call(
            "SET NOCOUNT ON;
             DECLARE @id_cuenta_pagar INT;
             EXEC dbo.sp_cuenta_pagar_registrar
                 @id_proveedor = ?,
                 @numero_factura = ?,
                 @fecha_emision = ?,
                 @detalle = ?,
                 @dias_credito = ?,
                 @pct_descuento_pronto_pago = ?,
                 @dias_descuento = ?,
                 @codigo_erp = ?,
                 @id_usuario = ?,
                 @id_cuenta_pagar = @id_cuenta_pagar OUTPUT;
             SELECT @id_cuenta_pagar AS id_cuenta_pagar;",
            [
                (int) $data['id_proveedor'],
                $data['numero_factura'],
                $data['fecha_emision'],
                $detalle,
                $data['dias_credito'] ?? null,
                $data['pct_descuento_pronto_pago'] ?? 0,
                $data['dias_descuento'] ?? null,
                $data['codigo_erp'] ?? null,
                $userId,
            ]
        );

        return (int) ($rows[0]['id_cuenta_pagar'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $userId): void
    {
        $detalle = json_encode($data['detalle'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($detalle === false) {
            $detalle = '[]';
        }

        Database::execute(
            'EXEC dbo.sp_cuenta_pagar_modificar
                 @id_cuenta_pagar = ?,
                 @id_proveedor = ?,
                 @numero_factura = ?,
                 @fecha_emision = ?,
                 @detalle = ?,
                 @dias_credito = ?,
                 @pct_descuento_pronto_pago = ?,
                 @dias_descuento = ?,
                 @codigo_erp = ?,
                 @id_usuario = ?;',
            [
                $id,
                (int) $data['id_proveedor'],
                $data['numero_factura'],
                $data['fecha_emision'],
                $detalle,
                $data['dias_credito'] ?? null,
                $data['pct_descuento_pronto_pago'] ?? 0,
                $data['dias_descuento'] ?? null,
                $data['codigo_erp'] ?? null,
                $userId,
            ]
        );
    }

    /**
     * Pasa a VENCIDA las cuentas con saldo cuyo vencimiento ya pasó. Es idempotente y solo
     * actualiza filas que cambian, así que se ejecuta antes de mostrar cuentas y panel.
     */
    public function refreshOverdue(): void
    {
        Database::call('EXEC dbo.sp_cuentas_actualizar_vencidas;');
    }

    public function annul(int $id, string $motivo, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_cuenta_pagar_anular @id_cuenta_pagar = ?, @motivo = ?, @id_usuario = ?;',
            [$id, $motivo, $userId]
        );
    }
}
