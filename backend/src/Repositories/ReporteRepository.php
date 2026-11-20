<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class ReporteRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function aging(?string $buscar = null, ?string $bucket = null): array
    {
        $rows = Database::call(
            'EXEC CuentasPorPagarDW.rpt.sp_antiguedad_actual @maximo = ?, @buscar = ?, @bucket = ?;',
            [500, $buscar, $bucket]
        );

        return Cast::rows($rows, ['id_proveedor', 'dias_vencido'], ['saldo_pendiente']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pagosMes(?string $desde = null, ?string $hasta = null): array
    {
        $rows = Database::call(
            'EXEC CuentasPorPagarDW.rpt.sp_pagos_por_mes @desde = ?, @hasta = ?;',
            [$desde, $hasta]
        );

        return Cast::rows($rows, ['cantidad_pagos'], ['monto_pagado', 'monto_descuento']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cumplimiento(?string $desde = null, ?string $hasta = null): array
    {
        $rows = Database::call(
            'EXEC CuentasPorPagarDW.rpt.sp_resumen_cumplimiento_plazo @desde = ?, @hasta = ?;',
            [$desde, $hasta]
        );

        return Cast::rows(
            $rows,
            ['cantidad_pagos', 'pagos_en_plazo', 'pagos_tarde'],
            ['pct_en_plazo']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function saldosProveedor(?string $buscar = null, ?float $saldoMinimo = null): array
    {
        $rows = Database::call(
            'EXEC CuentasPorPagarDW.rpt.sp_saldos_por_proveedor @buscar = ?, @saldo_minimo = ?;',
            [$buscar, $saldoMinimo]
        );

        return Cast::rows($rows, ['id_proveedor', 'facturas_abiertas'], ['saldo_pendiente']);
    }

    /**
     * Flujo de caja proyectado desde el OLTP (al día, no depende del ETL).
     *
     * @return array<int, array<string, mixed>>
     */
    public function flujoCaja(string $desde, string $hasta, string $agrupacion): array
    {
        $rows = Database::call(
            'EXEC dbo.sp_reporte_flujo_caja @desde = ?, @hasta = ?, @agrupacion = ?;',
            [$desde, $hasta, $agrupacion]
        );

        return Cast::rows(
            $rows,
            [],
            ['cuentas_vencidas', 'cuentas_por_pagar', 'pagos_programados', 'total_periodo', 'total_acumulado']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * Ejecuta el ETL completo y deja en la auditoría quién lo lanzó.
     *
     * @return array<string, mixed>
     */
    public function ejecutarEtl(int $userId): array
    {
        $rows = Database::call('SET NOCOUNT ON; EXEC CuentasPorPagarDW.etl.sp_ejecutar_todo;');
        $resultado = Cast::row($rows[0] ?? [], ['id_ejecucion']);

        Database::call(
            'EXEC dbo.sp_auditoria_registrar @id_usuario = ?, @tabla_afectada = ?, @id_registro = ?, @accion = ?;',
            [$userId, 'etl.ejecucion', (string) ($resultado['id_ejecucion'] ?? ''), 'EJECUTAR']
        );

        return $resultado;
    }

    public function etlUltimaCarga(): ?array
    {
        return Database::call('EXEC CuentasPorPagarDW.rpt.sp_etl_ultima_carga;')[0] ?? null;
    }
}
