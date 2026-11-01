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
        // Un solo procedimiento devuelve los cinco catálogos, en este orden
        [$bancos, $tiposPago, $estados, $roles, $metodos] = array_pad(
            Database::callSets('EXEC dbo.sp_catalogos_listar;'),
            5,
            []
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
        $row = Database::call('EXEC dbo.sp_dashboard_resumen;')[0] ?? [];

        return Cast::row(
            $row,
            ['cuentas_abiertas', 'cuentas_vencidas'],
            ['saldo_pendiente', 'saldo_vencido', 'pagos_mes']
        );
    }
}
