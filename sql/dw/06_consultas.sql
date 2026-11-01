/* =====================================================================================
   Procedimientos de consulta de reportes del Data Warehouse.
   La API los ejecuta en lugar de hacer SELECT directo sobre las vistas rpt.vw_*.
   Ejecutar después de 04_views.sql. Es idempotente.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

CREATE OR ALTER PROCEDURE rpt.sp_antiguedad_actual
    @maximo INT = 500
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP (@maximo) fecha_corte, id_proveedor, razon_social, numero_factura,
           fecha_emision, fecha_vencimiento, estado, saldo_pendiente,
           dias_vencido, bucket_antiguedad
    FROM rpt.vw_antiguedad_actual
    ORDER BY dias_vencido DESC, saldo_pendiente DESC;
END;
GO

CREATE OR ALTER PROCEDURE rpt.sp_pagos_por_mes
AS
BEGIN
    SET NOCOUNT ON;
    SELECT anio_mes, cantidad_pagos, monto_pagado, monto_descuento
    FROM rpt.vw_pagos_por_mes
    ORDER BY anio_mes DESC;
END;
GO

CREATE OR ALTER PROCEDURE rpt.sp_resumen_cumplimiento_plazo
AS
BEGIN
    SET NOCOUNT ON;
    SELECT anio_mes, cantidad_pagos, pagos_en_plazo, pagos_tarde, pct_en_plazo
    FROM rpt.vw_resumen_cumplimiento_plazo
    ORDER BY anio_mes DESC;
END;
GO

CREATE OR ALTER PROCEDURE rpt.sp_saldos_por_proveedor
AS
BEGIN
    SET NOCOUNT ON;
    SELECT id_proveedor, razon_social, facturas_abiertas, saldo_pendiente
    FROM rpt.vw_saldos_por_proveedor
    ORDER BY saldo_pendiente DESC;
END;
GO

CREATE OR ALTER PROCEDURE rpt.sp_etl_ultima_carga
AS
BEGIN
    SET NOCOUNT ON;
    SELECT id_ejecucion, proceso, fecha_inicio, fecha_fin, estado, mensaje_error
    FROM rpt.vw_etl_ultima_carga;
END;
GO
