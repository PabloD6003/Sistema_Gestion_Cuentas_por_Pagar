/* =====================================================================================
   Validaciones de calidad del Data Warehouse.
   Devuelve una fila por control; `resultado` debe ser 0 o `OK`.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET NOCOUNT ON;
GO

DECLARE @fecha_corte DATE = CAST(SYSDATETIME() AS DATE);
DECLARE @tiempo_key INT = CONVERT(INT, CONVERT(CHAR(8), @fecha_corte, 112));

SELECT 'facturas_contra_oltp' AS control,
       CASE WHEN (SELECT COUNT(*) FROM fact.facturas) =
                 (SELECT COUNT(*) FROM CuentasPorPagarDB.dbo.CP04_cuentas_por_pagar)
            THEN 'OK' ELSE 'ERROR' END AS resultado,
       (SELECT COUNT(*) FROM fact.facturas) AS dw,
       (SELECT COUNT(*) FROM CuentasPorPagarDB.dbo.CP04_cuentas_por_pagar) AS oltp;

SELECT 'pagos_contra_oltp' AS control,
       CASE WHEN (SELECT COUNT(*) FROM fact.pagos) =
                 (SELECT COUNT(*) FROM CuentasPorPagarDB.dbo.CP07_movimientos)
            THEN 'OK' ELSE 'ERROR' END AS resultado,
       (SELECT COUNT(*) FROM fact.pagos) AS dw,
       (SELECT COUNT(*) FROM CuentasPorPagarDB.dbo.CP07_movimientos) AS oltp;

SELECT 'saldo_facturas_no_negativo' AS control,
       CASE WHEN NOT EXISTS (SELECT 1 FROM fact.facturas WHERE saldo_pendiente < 0)
            THEN 'OK' ELSE 'ERROR' END AS resultado;

SELECT 'ecuacion_saldo_facturas' AS control,
       CASE WHEN NOT EXISTS (
                SELECT 1 FROM fact.facturas
                WHERE ABS((monto_total - monto_pagado - monto_descuento - monto_nota_credito + monto_nota_debito) - saldo_pendiente) > 0.01
            ) THEN 'OK' ELSE 'ERROR' END AS resultado;

SELECT 'scd2_un_solo_actual' AS control,
       CASE WHEN NOT EXISTS (
                SELECT id_proveedor FROM dim.proveedor
                WHERE id_proveedor > 0
                GROUP BY id_proveedor
                HAVING SUM(CASE WHEN es_actual = 1 THEN 1 ELSE 0 END) <> 1
            ) THEN 'OK' ELSE 'ERROR' END AS resultado;

SELECT 'aging_ultimo_dia' AS control,
       CASE WHEN (SELECT COUNT(*) FROM fact.aging_snapshot WHERE tiempo_key = @tiempo_key) =
                 (SELECT COUNT(*) FROM fact.facturas f
                  JOIN dim.estado e ON e.estado_key = f.estado_factura_key
                  WHERE f.saldo_pendiente > 0 AND e.codigo <> 'ANULADA')
            THEN 'OK' ELSE 'ERROR' END AS resultado;

SELECT 'etl_ultima_carga' AS control,
       CASE WHEN EXISTS (SELECT 1 FROM etl.ejecucion
                         WHERE estado = 'OK'
                           AND id_ejecucion = (SELECT MAX(id_ejecucion) FROM etl.ejecucion))
            THEN 'OK' ELSE 'ERROR' END AS resultado;
GO
