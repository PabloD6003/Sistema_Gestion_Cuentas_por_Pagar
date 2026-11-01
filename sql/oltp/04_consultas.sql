/* =====================================================================================
   Procedimientos de consulta para la aplicación.
   La API no ejecuta SELECT directos: toda lectura pasa por estos procedimientos, que a su
   vez leen las vistas de 02_lectura.sql. Así la aplicación solo necesita EXECUTE.
   Convenciones:
   - Los parámetros de filtro en NULL no filtran.
   - Los listados paginados devuelven dos conjuntos: primero el total y luego la página.
   - OPTION (RECOMPILE) en los listados con filtros opcionales para que el plan use los
     índices según los filtros que realmente llegan.
   Ejecutar después de 02_lectura.sql. Es idempotente.
   ===================================================================================== */
USE CuentasPorPagarDB;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* ---------- Proveedores ---------- */
CREATE OR ALTER PROCEDURE dbo.sp_proveedores_listar
    @buscar     VARCHAR(100) = NULL,
    @estado     BIT = NULL,
    @pagina     INT = 1,
    @por_pagina INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @patron VARCHAR(102) = '%' + NULLIF(LTRIM(RTRIM(@buscar)), '') + '%';

    SELECT COUNT(*) AS total
    FROM dbo.vw_proveedores
    WHERE (@patron IS NULL OR razon_social LIKE @patron OR identificacion_tributaria LIKE @patron)
      AND (@estado IS NULL OR estado = @estado)
    OPTION (RECOMPILE);

    SELECT id_proveedor, identificacion_tributaria, razon_social, nombre_comercial, pais,
           direccion, telefono, correo, contacto_nombre, dias_credito, banco,
           cuenta_bancaria, estado, fecha_registro, codigo_erp, calificacion_promedio, evaluaciones
    FROM dbo.vw_proveedores
    WHERE (@patron IS NULL OR razon_social LIKE @patron OR identificacion_tributaria LIKE @patron)
      AND (@estado IS NULL OR estado = @estado)
    ORDER BY razon_social
    OFFSET (@pagina - 1) * @por_pagina ROWS FETCH NEXT @por_pagina ROWS ONLY
    OPTION (RECOMPILE);
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_proveedor_obtener
    @id_proveedor INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT id_proveedor, identificacion_tributaria, razon_social, nombre_comercial, pais,
           direccion, telefono, correo, contacto_nombre, dias_credito, banco,
           cuenta_bancaria, estado, fecha_registro, codigo_erp, calificacion_promedio, evaluaciones
    FROM dbo.vw_proveedores
    WHERE id_proveedor = @id_proveedor;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_proveedor_evaluaciones
    @id_proveedor INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT id_evaluacion, id_proveedor, fecha_evaluacion, calificacion, comentario
    FROM dbo.vw_evaluaciones_proveedor
    WHERE id_proveedor = @id_proveedor
    ORDER BY fecha_evaluacion DESC, id_evaluacion DESC;
END;
GO

/* ---------- Cuentas por pagar ---------- */
CREATE OR ALTER PROCEDURE dbo.sp_cuentas_listar
    @buscar        VARCHAR(100) = NULL,
    @estado        VARCHAR(30) = NULL,
    @id_proveedor  INT = NULL,
    @solo_vencidas BIT = 0,
    @pagina        INT = 1,
    @por_pagina    INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @patron VARCHAR(102) = '%' + NULLIF(LTRIM(RTRIM(@buscar)), '') + '%';
    SET @estado = NULLIF(@estado, '');

    SELECT COUNT(*) AS total
    FROM dbo.vw_cuentas_por_pagar
    WHERE (@patron IS NULL OR numero_factura LIKE @patron OR razon_social LIKE @patron)
      AND (@estado IS NULL OR estado = @estado)
      AND (@id_proveedor IS NULL OR id_proveedor = @id_proveedor)
      AND (@solo_vencidas = 0 OR (dias_vencido > 0 AND saldo_pendiente > 0 AND estado <> 'ANULADA'))
    OPTION (RECOMPILE);

    SELECT id_cuenta_pagar, id_proveedor, razon_social, numero_factura, fecha_emision,
           fecha_vencimiento, monto_total, saldo_pendiente, estado, estado_nombre, dias_vencido
    FROM dbo.vw_cuentas_por_pagar
    WHERE (@patron IS NULL OR numero_factura LIKE @patron OR razon_social LIKE @patron)
      AND (@estado IS NULL OR estado = @estado)
      AND (@id_proveedor IS NULL OR id_proveedor = @id_proveedor)
      AND (@solo_vencidas = 0 OR (dias_vencido > 0 AND saldo_pendiente > 0 AND estado <> 'ANULADA'))
    ORDER BY fecha_vencimiento ASC, id_cuenta_pagar DESC
    OFFSET (@pagina - 1) * @por_pagina ROWS FETCH NEXT @por_pagina ROWS ONLY
    OPTION (RECOMPILE);
END;
GO

-- Encabezado y líneas de la factura. Es editable mientras no esté anulada ni tenga
-- movimientos vigentes (la misma regla que valida sp_cuenta_pagar_modificar).
CREATE OR ALTER PROCEDURE dbo.sp_cuenta_pagar_obtener
    @id_cuenta_pagar INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT c.id_cuenta_pagar, c.id_proveedor, c.razon_social, c.numero_factura, c.fecha_emision,
           c.fecha_vencimiento, c.dias_credito, c.pct_descuento_pronto_pago, c.fecha_limite_descuento,
           c.subtotal, c.monto_impuesto, c.monto_total, c.saldo_pendiente, c.estado, c.estado_nombre,
           c.dias_vencido, c.fecha_registro, c.codigo_erp,
           CAST(IIF(c.estado <> 'ANULADA' AND NOT EXISTS (
                    SELECT 1 FROM dbo.vw_movimientos m
                    WHERE m.id_cuenta_pagar = c.id_cuenta_pagar AND m.estado <> 'ANULADO'), 1, 0) AS BIT) AS editable
    FROM dbo.vw_cuentas_por_pagar c
    WHERE c.id_cuenta_pagar = @id_cuenta_pagar;

    SELECT id_detalle, id_cuenta_pagar, numero_linea, descripcion, cantidad,
           precio_unitario, pct_impuesto, monto_impuesto, total_linea
    FROM dbo.vw_cuentas_por_pagar_detalle
    WHERE id_cuenta_pagar = @id_cuenta_pagar
    ORDER BY numero_linea;
END;
GO

/* ---------- Pagos y tesorería ---------- */
CREATE OR ALTER PROCEDURE dbo.sp_movimientos_listar
    @id_cuenta_pagar INT = NULL,
    @estado          VARCHAR(30) = NULL,
    @tipo            VARCHAR(50) = NULL,
    @id_banco        INT = NULL,
    @conciliado      BIT = NULL,
    @buscar          VARCHAR(100) = NULL,
    @pagina          INT = 1,
    @por_pagina      INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @patron VARCHAR(102) = '%' + NULLIF(LTRIM(RTRIM(@buscar)), '') + '%';
    SET @estado = NULLIF(@estado, '');
    SET @tipo = NULLIF(@tipo, '');

    SELECT COUNT(*) AS total
    FROM dbo.vw_movimientos
    WHERE (@id_cuenta_pagar IS NULL OR id_cuenta_pagar = @id_cuenta_pagar)
      AND (@estado IS NULL OR estado = @estado)
      AND (@tipo IS NULL OR tipo_movimiento = @tipo)
      AND (@id_banco IS NULL OR id_banco = @id_banco)
      AND (@conciliado IS NULL OR conciliado = @conciliado)
      AND (@patron IS NULL OR numero_factura LIKE @patron OR razon_social LIKE @patron OR referencia LIKE @patron)
    OPTION (RECOMPILE);

    SELECT id_movimiento, id_cuenta_pagar, numero_factura, id_proveedor, razon_social,
           numero_movimiento, fecha_movimiento, tipo_movimiento, monto, tipo_pago, id_banco,
           referencia, descripcion, estado, conciliado, id_lote, id_programacion,
           id_movimiento_origen, fecha_registro
    FROM dbo.vw_movimientos
    WHERE (@id_cuenta_pagar IS NULL OR id_cuenta_pagar = @id_cuenta_pagar)
      AND (@estado IS NULL OR estado = @estado)
      AND (@tipo IS NULL OR tipo_movimiento = @tipo)
      AND (@id_banco IS NULL OR id_banco = @id_banco)
      AND (@conciliado IS NULL OR conciliado = @conciliado)
      AND (@patron IS NULL OR numero_factura LIKE @patron OR razon_social LIKE @patron OR referencia LIKE @patron)
    ORDER BY fecha_movimiento DESC, id_movimiento DESC
    OFFSET (@pagina - 1) * @por_pagina ROWS FETCH NEXT @por_pagina ROWS ONLY
    OPTION (RECOMPILE);
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_lotes_listar
    @maximo INT = 100
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP (@maximo) id_lote, id_banco, nombre_banco, fecha_envio, cantidad_pagos,
           monto_total, estado, estado_nombre
    FROM dbo.vw_lotes
    ORDER BY id_lote DESC;
END;
GO

/* ---------- Catálogos y panel ---------- */
-- Devuelve cinco conjuntos, en orden: bancos, tipos de pago, estados, roles y métodos de pago.
CREATE OR ALTER PROCEDURE dbo.sp_catalogos_listar
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id_banco, nombre_banco, numero_cuenta, estado
    FROM dbo.vw_bancos
    ORDER BY nombre_banco;

    SELECT id_tipo_pago, nombre, id_metodo_pago, metodo_pago, id_banco, nombre_banco,
           requiere_banco, estado
    FROM dbo.vw_tipos_pago
    WHERE estado = 1
    ORDER BY nombre, nombre_banco;

    SELECT id_estado, ambito, codigo, nombre
    FROM dbo.vw_catalogos_estado
    ORDER BY ambito, nombre;

    SELECT id_rol, nombre, descripcion, nivel_acceso, puede_aprobar_pagos, estado
    FROM dbo.vw_roles
    WHERE estado = 1
    ORDER BY nombre;

    SELECT DISTINCT id_metodo_pago, metodo_pago AS nombre, requiere_banco
    FROM dbo.vw_tipos_pago
    WHERE metodo_pago IS NOT NULL
    ORDER BY metodo_pago;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_dashboard_resumen
AS
BEGIN
    SET NOCOUNT ON;
    SELECT * FROM dbo.vw_dashboard_resumen;
END;
GO

/* ---------- Usuarios ---------- */
CREATE OR ALTER PROCEDURE dbo.sp_usuario_perfil
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT u.id_usuario, u.id_rol, u.rol, u.nombre_usuario, u.nombre_completo,
           u.correo, u.estado, r.puede_aprobar_pagos, r.nivel_acceso
    FROM dbo.vw_usuarios u
    JOIN dbo.vw_roles r ON r.id_rol = u.id_rol
    WHERE u.id_usuario = @id_usuario;
END;
GO

/* ---------- Permisos ---------- */
GRANT EXECUTE ON dbo.sp_proveedores_listar     TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_proveedor_obtener      TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_proveedor_evaluaciones TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_cuentas_listar         TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_cuenta_pagar_obtener   TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_movimientos_listar     TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_lotes_listar           TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_catalogos_listar       TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_dashboard_resumen      TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_usuario_perfil         TO rol_cxp_operacion;

GRANT EXECUTE ON dbo.sp_proveedores_listar     TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_proveedor_obtener      TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_proveedor_evaluaciones TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_cuentas_listar         TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_cuenta_pagar_obtener   TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_movimientos_listar     TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_lotes_listar           TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_catalogos_listar       TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_dashboard_resumen      TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_usuario_perfil         TO rol_cxp_auditoria;
GO
