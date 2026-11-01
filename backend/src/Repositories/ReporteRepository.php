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
    public function aging(): array
    {
        $rows = Database::call('EXEC CuentasPorPagarDW.rpt.sp_antiguedad_actual @maximo = ?;', [500]);

        return Cast::rows($rows, ['id_proveedor', 'dias_vencido'], ['saldo_pendiente']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pagosMes(): array
    {
        $rows = Database::call('EXEC CuentasPorPagarDW.rpt.sp_pagos_por_mes;');

        return Cast::rows($rows, ['cantidad_pagos'], ['monto_pagado', 'monto_descuento']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cumplimiento(): array
    {
        $rows = Database::call('EXEC CuentasPorPagarDW.rpt.sp_resumen_cumplimiento_plazo;');

        return Cast::rows(
            $rows,
            ['cantidad_pagos', 'pagos_en_plazo', 'pagos_tarde'],
            ['pct_en_plazo']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function saldosProveedor(): array
    {
        $rows = Database::call('EXEC CuentasPorPagarDW.rpt.sp_saldos_por_proveedor;');

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
    public function etlUltimaCarga(): ?array
    {
        return Database::call('EXEC CuentasPorPagarDW.rpt.sp_etl_ultima_carga;')[0] ?? null;
    }
}
