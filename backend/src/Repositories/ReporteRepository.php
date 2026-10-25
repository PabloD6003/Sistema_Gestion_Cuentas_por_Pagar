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
        $rows = Database::fetchAll(
            'SELECT TOP (500) fecha_corte, id_proveedor, razon_social, numero_factura,
                    fecha_emision, fecha_vencimiento, estado, saldo_pendiente,
                    dias_vencido, bucket_antiguedad
             FROM CuentasPorPagarDW.rpt.vw_antiguedad_actual
             ORDER BY dias_vencido DESC, saldo_pendiente DESC'
        );

        return Cast::rows($rows, ['id_proveedor', 'dias_vencido'], ['saldo_pendiente']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pagosMes(): array
    {
        $rows = Database::fetchAll(
            'SELECT anio_mes, cantidad_pagos, monto_pagado, monto_descuento
             FROM CuentasPorPagarDW.rpt.vw_pagos_por_mes
             ORDER BY anio_mes DESC'
        );

        return Cast::rows($rows, ['cantidad_pagos'], ['monto_pagado', 'monto_descuento']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cumplimiento(): array
    {
        $rows = Database::fetchAll(
            'SELECT anio_mes, cantidad_pagos, pagos_en_plazo, pagos_tarde, pct_en_plazo
             FROM CuentasPorPagarDW.rpt.vw_resumen_cumplimiento_plazo
             ORDER BY anio_mes DESC'
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
    public function saldosProveedor(): array
    {
        $rows = Database::fetchAll(
            'SELECT id_proveedor, razon_social, facturas_abiertas, saldo_pendiente
             FROM CuentasPorPagarDW.rpt.vw_saldos_por_proveedor
             ORDER BY saldo_pendiente DESC'
        );

        return Cast::rows($rows, ['id_proveedor', 'facturas_abiertas'], ['saldo_pendiente']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function etlUltimaCarga(): ?array
    {
        return Database::fetchOne(
            'SELECT id_ejecucion, proceso, fecha_inicio, fecha_fin, estado, mensaje_error
             FROM CuentasPorPagarDW.rpt.vw_etl_ultima_carga'
        );
    }
}
