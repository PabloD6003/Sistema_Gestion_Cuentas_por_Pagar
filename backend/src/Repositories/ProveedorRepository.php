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
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(razon_social LIKE ? OR identificacion_tributaria LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        if (($filters['estado'] ?? null) !== null) {
            $where[] = 'estado = ?';
            $params[] = (int) $filters['estado'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $totalRow = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM dbo.vw_proveedores' . $whereSql,
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = Database::fetchAll(
            'SELECT id_proveedor, identificacion_tributaria, razon_social, nombre_comercial, pais,
                    direccion, telefono, correo, contacto_nombre, dias_credito, banco,
                    cuenta_bancaria, estado, fecha_registro, codigo_erp
             FROM dbo.vw_proveedores' . $whereSql . '
             ORDER BY razon_social
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
            array_merge($params, [$offset, $perPage])
        );

        return [
            'items' => Cast::rows($rows, ['id_proveedor', 'dias_credito'], [], ['estado']),
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
            'SELECT id_proveedor, identificacion_tributaria, razon_social, nombre_comercial, pais,
                    direccion, telefono, correo, contacto_nombre, dias_credito, banco,
                    cuenta_bancaria, estado, fecha_registro, codigo_erp
             FROM dbo.vw_proveedores WHERE id_proveedor = ?',
            [$id]
        );

        return $row === null ? null : Cast::row($row, ['id_proveedor', 'dias_credito'], [], ['estado']);
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
}
