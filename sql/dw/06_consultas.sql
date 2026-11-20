/* =====================================================================================
   Procedimientos de consulta de reportes del Data Warehouse.
   La API los ejecuta en lugar de hacer SELECT directo sobre las vistas rpt.vw_*.
   Ejecutar después de 04_views.sql. Es idempotente.

   Todos los filtros son opcionales: con los parámetros en NULL cada procedimiento
   devuelve el conjunto completo, igual que antes de parametrizarlos.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* Antigüedad de saldos al último corte.
   @buscar filtra por razón social o número de factura; @bucket por tramo de días. */
CREATE OR ALTER PROCEDURE rpt.sp_antiguedad_actual
    @maximo INT = 500,
    @buscar VARCHAR(150) = NULL,
    @bucket VARCHAR(10) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @buscar = NULLIF(LTRIM(RTRIM(@buscar)), '');
    SET @bucket = NULLIF(LTRIM(RTRIM(@bucket)), '');

    IF @bucket IS NOT NULL AND @bucket NOT IN ('0-30', '31-60', '61-90', '90+')
        THROW 52010, 'El tramo de antigüedad debe ser 0-30, 31-60, 61-90 o 90+.', 1;

    SELECT TOP (@maximo) fecha_corte, id_proveedor, razon_social, numero_factura,
           fecha_emision, fecha_vencimiento, estado, saldo_pendiente,
           dias_vencido, bucket_antiguedad
    FROM rpt.vw_antiguedad_actual
    WHERE (@buscar IS NULL
           OR razon_social LIKE '%' + @buscar + '%'
           OR numero_factura LIKE '%' + @buscar + '%')
      AND (@bucket IS NULL OR bucket_antiguedad = @bucket)
    ORDER BY dias_vencido DESC, saldo_pendiente DESC;
END;
GO

/* Pagos agrupados por mes. @desde y @hasta son meses en formato YYYY-MM. */
CREATE OR ALTER PROCEDURE rpt.sp_pagos_por_mes
    @desde CHAR(7) = NULL,
    @hasta CHAR(7) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @desde = NULLIF(LTRIM(RTRIM(@desde)), '');
    SET @hasta = NULLIF(LTRIM(RTRIM(@hasta)), '');

    IF @desde IS NOT NULL AND @hasta IS NOT NULL AND @hasta < @desde
        THROW 52011, 'El mes final no puede ser anterior al inicial.', 1;

    SELECT anio_mes, cantidad_pagos, monto_pagado, monto_descuento
    FROM rpt.vw_pagos_por_mes
    WHERE (@desde IS NULL OR anio_mes >= @desde)
      AND (@hasta IS NULL OR anio_mes <= @hasta)
    ORDER BY anio_mes DESC;
END;
GO

/* Cumplimiento del plazo de pago por mes. @desde y @hasta en formato YYYY-MM. */
CREATE OR ALTER PROCEDURE rpt.sp_resumen_cumplimiento_plazo
    @desde CHAR(7) = NULL,
    @hasta CHAR(7) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @desde = NULLIF(LTRIM(RTRIM(@desde)), '');
    SET @hasta = NULLIF(LTRIM(RTRIM(@hasta)), '');

    IF @desde IS NOT NULL AND @hasta IS NOT NULL AND @hasta < @desde
        THROW 52011, 'El mes final no puede ser anterior al inicial.', 1;

    SELECT anio_mes, cantidad_pagos, pagos_en_plazo, pagos_tarde, pct_en_plazo
    FROM rpt.vw_resumen_cumplimiento_plazo
    WHERE (@desde IS NULL OR anio_mes >= @desde)
      AND (@hasta IS NULL OR anio_mes <= @hasta)
    ORDER BY anio_mes DESC;
END;
GO

/* Saldo abierto por proveedor.
   @buscar filtra por razón social; @saldo_minimo deja fuera a los proveedores pequeños. */
CREATE OR ALTER PROCEDURE rpt.sp_saldos_por_proveedor
    @buscar        VARCHAR(150) = NULL,
    @saldo_minimo  DECIMAL(18,2) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @buscar = NULLIF(LTRIM(RTRIM(@buscar)), '');

    IF @saldo_minimo IS NOT NULL AND @saldo_minimo < 0
        THROW 52012, 'El saldo mínimo no puede ser negativo.', 1;

    SELECT id_proveedor, razon_social, facturas_abiertas, saldo_pendiente
    FROM rpt.vw_saldos_por_proveedor
    WHERE (@buscar IS NULL OR razon_social LIKE '%' + @buscar + '%')
      AND (@saldo_minimo IS NULL OR saldo_pendiente >= @saldo_minimo)
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
