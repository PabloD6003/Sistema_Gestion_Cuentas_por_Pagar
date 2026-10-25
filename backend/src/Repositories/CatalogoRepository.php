<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class CatalogoRepository
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function all(): array
    {
        $bancos = Database::fetchAll(
            'SELECT id_banco, nombre_banco, numero_cuenta, estado FROM dbo.vw_bancos ORDER BY nombre_banco'
        );

        $tiposPago = Database::fetchAll(
            'SELECT id_tipo_pago, nombre, id_metodo_pago, metodo_pago, id_banco, nombre_banco,
                    requiere_banco, estado
             FROM dbo.vw_tipos_pago
             WHERE estado = 1
             ORDER BY nombre, nombre_banco'
        );

        $estados = Database::fetchAll(
            'SELECT id_estado, ambito, codigo, nombre FROM dbo.vw_catalogos_estado ORDER BY ambito, nombre'
        );

        $roles = Database::fetchAll(
            'SELECT id_rol, nombre, descripcion, nivel_acceso, puede_aprobar_pagos, estado
             FROM dbo.vw_roles WHERE estado = 1 ORDER BY nombre'
        );

        $metodos = Database::fetchAll(
            'SELECT DISTINCT id_metodo_pago, metodo_pago AS nombre, requiere_banco
             FROM dbo.vw_tipos_pago
             WHERE metodo_pago IS NOT NULL
             ORDER BY metodo_pago'
        );

        return [
            'bancos' => Cast::rows($bancos, ['id_banco'], [], ['estado']),
            'tipos_pago' => Cast::rows(
                $tiposPago,
                ['id_tipo_pago', 'id_metodo_pago', 'id_banco'],
                [],
                ['requiere_banco', 'estado']
            ),
            'estados' => Cast::rows($estados, ['id_estado']),
            'roles' => Cast::rows($roles, ['id_rol', 'nivel_acceso'], [], ['puede_aprobar_pagos', 'estado']),
            'metodos_pago' => Cast::rows($metodos, ['id_metodo_pago'], [], ['requiere_banco']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $row = Database::fetchOne('SELECT * FROM dbo.vw_dashboard_resumen') ?? [];

        return Cast::row(
            $row,
            ['cuentas_abiertas', 'cuentas_vencidas'],
            ['saldo_pendiente', 'saldo_vencido', 'pagos_mes']
        );
    }
}
