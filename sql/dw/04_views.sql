/* =====================================================================================
   Vistas de reportes del Data Warehouse.
   Ejecutar después de 03_etl.sql.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

CREATE OR ALTER VIEW rpt.vw_antiguedad_actual
AS
WITH ultimo AS (
    SELECT MAX(tiempo_key) AS tiempo_key FROM fact.aging_snapshot
)
SELECT t.fecha AS fecha_corte,
       p.id_proveedor,
       p.razon_social,
       f.numero_factura,
       f.fecha_emision,
       f.fecha_vencimiento,
       e.codigo AS estado,
       a.saldo_pendiente,
       a.dias_vencido,
       a.bucket_antiguedad
FROM fact.aging_snapshot a
JOIN ultimo u             ON u.tiempo_key = a.tiempo_key
JOIN dim.tiempo t         ON t.tiempo_key = a.tiempo_key
JOIN dim.proveedor p      ON p.proveedor_key = a.proveedor_key
JOIN dim.factura f        ON f.factura_key = a.factura_key
JOIN dim.estado e         ON e.estado_key = a.estado_factura_key;
GO

CREATE OR ALTER VIEW rpt.vw_antiguedad_historica
AS
WITH cierre_mes AS (
    SELECT t.anio_mes, MAX(a.tiempo_key) AS tiempo_key
    FROM fact.aging_snapshot a
    JOIN dim.tiempo t ON t.tiempo_key = a.tiempo_key
    GROUP BY t.anio_mes
)
SELECT c.anio_mes,
       a.bucket_antiguedad,
       COUNT(*) AS facturas,
       SUM(a.saldo_pendiente) AS saldo_pendiente
FROM cierre_mes c
JOIN fact.aging_snapshot a ON a.tiempo_key = c.tiempo_key
GROUP BY c.anio_mes, a.bucket_antiguedad;
GO

CREATE OR ALTER VIEW rpt.vw_pagos_por_mes
AS
SELECT t.anio_mes,
       COUNT(*) AS cantidad_pagos,
       SUM(p.monto_pago) AS monto_pagado,
       SUM(p.monto_descuento) AS monto_descuento
FROM fact.pagos p
JOIN dim.tiempo t             ON t.tiempo_key = p.tiempo_pago_key
JOIN dim.tipo_movimiento tm   ON tm.tipo_movimiento_key = p.tipo_movimiento_key
JOIN dim.estado e             ON e.estado_key = p.estado_movimiento_key
WHERE tm.nombre = 'Pago'
  AND e.codigo = 'APROBADO'
GROUP BY t.anio_mes;
GO

CREATE OR ALTER VIEW rpt.vw_pagos_por_proveedor
AS
SELECT p.id_proveedor,
       p.razon_social,
       COUNT(*) AS cantidad_pagos,
       SUM(f.monto_pago) AS monto_pagado,
       SUM(f.monto_descuento) AS monto_descuento
FROM fact.pagos f
JOIN dim.proveedor p          ON p.proveedor_key = f.proveedor_key
JOIN dim.tipo_movimiento tm   ON tm.tipo_movimiento_key = f.tipo_movimiento_key
JOIN dim.estado e             ON e.estado_key = f.estado_movimiento_key
WHERE tm.nombre = 'Pago'
  AND e.codigo = 'APROBADO'
GROUP BY p.id_proveedor, p.razon_social;
GO

CREATE OR ALTER VIEW rpt.vw_cumplimiento_plazo_pago
AS
SELECT f.id_movimiento,
       df.numero_factura,
       p.razon_social,
       t.fecha AS fecha_pago,
       df.fecha_vencimiento,
       f.dias_anticipacion,
       CASE WHEN f.dias_anticipacion >= 0 THEN 'EN_PLAZO' ELSE 'TARDE' END AS cumplimiento
FROM fact.pagos f
JOIN dim.tiempo t             ON t.tiempo_key = f.tiempo_pago_key
JOIN dim.factura df           ON df.factura_key = f.factura_key
JOIN dim.proveedor p          ON p.proveedor_key = f.proveedor_key
JOIN dim.tipo_movimiento tm   ON tm.tipo_movimiento_key = f.tipo_movimiento_key
JOIN dim.estado e             ON e.estado_key = f.estado_movimiento_key
WHERE tm.nombre = 'Pago'
  AND e.codigo = 'APROBADO';
GO

CREATE OR ALTER VIEW rpt.vw_resumen_cumplimiento_plazo
AS
SELECT t.anio_mes,
       COUNT(*) AS cantidad_pagos,
       SUM(CASE WHEN f.dias_anticipacion >= 0 THEN 1 ELSE 0 END) AS pagos_en_plazo,
       SUM(CASE WHEN f.dias_anticipacion < 0 THEN 1 ELSE 0 END) AS pagos_tarde,
       CAST(100.0 * SUM(CASE WHEN f.dias_anticipacion >= 0 THEN 1 ELSE 0 END) / COUNT(*) AS DECIMAL(5,2)) AS pct_en_plazo
FROM fact.pagos f
JOIN dim.tiempo t             ON t.tiempo_key = f.tiempo_pago_key
JOIN dim.tipo_movimiento tm   ON tm.tipo_movimiento_key = f.tipo_movimiento_key
JOIN dim.estado e             ON e.estado_key = f.estado_movimiento_key
WHERE tm.nombre = 'Pago'
  AND e.codigo = 'APROBADO'
GROUP BY t.anio_mes;
GO

CREATE OR ALTER VIEW rpt.vw_descuentos_obtenidos
AS
SELECT t.anio_mes,
       p.id_proveedor,
       p.razon_social,
       COUNT(*) AS cantidad_descuentos,
       SUM(f.monto_descuento) AS monto_descuento
FROM fact.pagos f
JOIN dim.tiempo t             ON t.tiempo_key = f.tiempo_pago_key
JOIN dim.proveedor p          ON p.proveedor_key = f.proveedor_key
JOIN dim.estado e             ON e.estado_key = f.estado_movimiento_key
WHERE f.monto_descuento > 0
  AND e.codigo = 'APROBADO'
GROUP BY t.anio_mes, p.id_proveedor, p.razon_social;
GO

CREATE OR ALTER VIEW rpt.vw_evolucion_cuentas_por_pagar
AS
SELECT t.anio_mes,
       e.codigo AS estado,
       COUNT(*) AS cantidad_facturas,
       SUM(f.monto_total) AS monto_total,
       SUM(f.saldo_pendiente) AS saldo_pendiente
FROM fact.facturas f
JOIN dim.tiempo t     ON t.tiempo_key = f.tiempo_emision_key
JOIN dim.estado e     ON e.estado_key = f.estado_factura_key
GROUP BY t.anio_mes, e.codigo;
GO

CREATE OR ALTER VIEW rpt.vw_saldos_por_proveedor
AS
SELECT p.id_proveedor,
       p.razon_social,
       COUNT(*) AS facturas_abiertas,
       SUM(f.saldo_pendiente) AS saldo_pendiente
FROM fact.facturas f
JOIN dim.proveedor p  ON p.proveedor_key = f.proveedor_key
JOIN dim.estado e     ON e.estado_key = f.estado_factura_key
WHERE f.saldo_pendiente > 0
  AND e.codigo <> 'ANULADA'
GROUP BY p.id_proveedor, p.razon_social;
GO

CREATE OR ALTER VIEW rpt.vw_facturas_abiertas
AS
SELECT f.id_cuenta_pagar,
       df.numero_factura,
       p.id_proveedor,
       p.razon_social,
       t.fecha AS fecha_emision,
       tv.fecha AS fecha_vencimiento,
       f.monto_total,
       f.monto_pagado,
       f.monto_descuento,
       f.saldo_pendiente,
       f.dias_vencido,
       e.codigo AS estado
FROM fact.facturas f
JOIN dim.factura df     ON df.factura_key = f.factura_key
JOIN dim.proveedor p    ON p.proveedor_key = f.proveedor_key
JOIN dim.tiempo t       ON t.tiempo_key = f.tiempo_emision_key
JOIN dim.tiempo tv      ON tv.tiempo_key = f.tiempo_vencimiento_key
JOIN dim.estado e       ON e.estado_key = f.estado_factura_key
WHERE f.saldo_pendiente > 0
  AND e.codigo <> 'ANULADA';
GO

CREATE OR ALTER VIEW rpt.vw_etl_historial
AS
SELECT id_ejecucion, proceso, fecha_inicio, fecha_fin, estado, mensaje_error
FROM etl.ejecucion;
GO

CREATE OR ALTER VIEW rpt.vw_etl_ultima_carga
AS
SELECT TOP (1) id_ejecucion, proceso, fecha_inicio, fecha_fin, estado, mensaje_error
FROM etl.ejecucion
ORDER BY id_ejecucion DESC;
GO
