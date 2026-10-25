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
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(numero_factura LIKE ? OR razon_social LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $estado = $filters['estado'] ?? null;
        if (is_string($estado) && $estado !== '') {
            $where[] = 'estado = ?';
            $params[] = $estado;
        }

        if (($filters['id_proveedor'] ?? null) !== null) {
            $where[] = 'id_proveedor = ?';
            $params[] = (int) $filters['id_proveedor'];
        }

        if (($filters['vencidas'] ?? false) === true) {
            $where[] = 'dias_vencido > 0 AND saldo_pendiente > 0 AND estado <> ?';
            $params[] = 'ANULADA';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $totalRow = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM dbo.vw_cuentas_por_pagar' . $whereSql,
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT id_cuenta_pagar, id_proveedor, razon_social, numero_factura, fecha_emision,
                    fecha_vencimiento, monto_total, saldo_pendiente, estado, estado_nombre, dias_vencido
             FROM dbo.vw_cuentas_por_pagar' . $whereSql . '
             ORDER BY fecha_vencimiento ASC, id_cuenta_pagar DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
            array_merge($params, [$offset, $perPage])
        );

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
        $row = Database::fetchOne(
            'SELECT id_cuenta_pagar, id_proveedor, razon_social, numero_factura, fecha_emision,
                    fecha_vencimiento, dias_credito, pct_descuento_pronto_pago, fecha_limite_descuento,
                    subtotal, monto_impuesto, monto_total, saldo_pendiente, estado, estado_nombre,
                    dias_vencido, fecha_registro, codigo_erp
             FROM dbo.vw_cuentas_por_pagar WHERE id_cuenta_pagar = ?',
            [$id]
        );

        if ($row === null) {
            return null;
        }

        $detalle = Database::fetchAll(
            'SELECT id_detalle, id_cuenta_pagar, numero_linea, descripcion, cantidad,
                    precio_unitario, pct_impuesto, monto_impuesto, total_linea
             FROM dbo.vw_cuentas_por_pagar_detalle
             WHERE id_cuenta_pagar = ?
             ORDER BY numero_linea',
            [$id]
        );

        $row = Cast::row(
            $row,
            ['id_cuenta_pagar', 'id_proveedor', 'dias_credito', 'dias_vencido'],
            ['pct_descuento_pronto_pago', 'subtotal', 'monto_impuesto', 'monto_total', 'saldo_pendiente']
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

    public function annul(int $id, int $userId): void
    {
        Database::execute(
            'EXEC dbo.sp_cuenta_pagar_anular @id_cuenta_pagar = ?, @id_usuario = ?;',
            [$id, $userId]
        );
    }
}
