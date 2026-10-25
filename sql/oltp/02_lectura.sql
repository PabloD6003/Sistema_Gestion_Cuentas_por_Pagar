/* =====================================================================================
   Capa de lectura para la aplicación.
   Solo se exponen vistas; las tablas base siguen sin SELECT para los roles de la app.
   Ejecutar después de 01_correcciones.sql.
   ===================================================================================== */
USE CuentasPorPagarDB;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

CREATE OR ALTER VIEW dbo.vw_proveedores
AS
SELECT p.id_proveedor,
       p.identificacion_tributaria,
       p.razon_social,
       p.nombre_comercial,
       p.pais,
       p.direccion,
       p.telefono,
       p.correo,
       p.contacto_nombre,
       p.dias_credito,
       p.banco,
       p.cuenta_bancaria,
       p.estado,
       p.fecha_registro,
       p.codigo_erp
FROM dbo.CP01_proveedores p;
GO

CREATE OR ALTER VIEW dbo.vw_cuentas_por_pagar
AS
SELECT c.id_cuenta_pagar,
       c.id_proveedor,
       p.razon_social,
       c.numero_factura,
       c.fecha_emision,
       c.fecha_vencimiento,
       c.dias_credito,
       c.pct_descuento_pronto_pago,
       c.fecha_limite_descuento,
       c.subtotal,
       c.monto_impuesto,
       c.monto_total,
       c.saldo_pendiente,
       c.id_estado,
       e.codigo AS estado,
       e.nombre AS estado_nombre,
       c.fecha_registro,
       c.codigo_erp,
       DATEDIFF(DAY, c.fecha_vencimiento, CAST(SYSDATETIME() AS DATE)) AS dias_vencido
FROM dbo.CP04_cuentas_por_pagar c
JOIN dbo.CP01_proveedores p ON p.id_proveedor = c.id_proveedor
JOIN dbo.CP08_estados e     ON e.id_estado = c.id_estado;
GO

CREATE OR ALTER VIEW dbo.vw_cuentas_por_pagar_detalle
AS
SELECT d.id_detalle,
       d.id_cuenta_pagar,
       d.numero_linea,
       d.descripcion,
       d.cantidad,
       d.precio_unitario,
       d.pct_impuesto,
       d.monto_impuesto,
       d.total_linea
FROM dbo.CP05_cuentas_por_pagar_detalle d;
GO

CREATE OR ALTER VIEW dbo.vw_movimientos
AS
SELECT m.id_movimiento,
       m.id_cuenta_pagar,
       c.numero_factura,
       c.id_proveedor,
       p.razon_social,
       m.numero_movimiento,
       m.fecha_movimiento,
       m.id_tipo_movimiento,
       tm.nombre AS tipo_movimiento,
       m.monto,
       m.id_tipo_pago,
       tp.nombre AS tipo_pago,
       m.referencia,
       m.descripcion,
       m.id_estado,
       em.codigo AS estado,
       em.nombre AS estado_nombre,
       m.conciliado,
       m.fecha_conciliacion,
       m.id_lote,
       m.id_programacion,
       m.id_movimiento_origen,
       m.id_usuario_registro,
       m.fecha_registro,
       m.id_usuario_aprueba,
       m.fecha_aprobacion,
       m.codigo_erp
FROM dbo.CP07_movimientos m
JOIN dbo.CP04_cuentas_por_pagar c ON c.id_cuenta_pagar = m.id_cuenta_pagar
JOIN dbo.CP01_proveedores p       ON p.id_proveedor = c.id_proveedor
JOIN dbo.CP02_tipos_movimiento tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
JOIN dbo.CP08_estados em          ON em.id_estado = m.id_estado
LEFT JOIN dbo.CP03_tipos_pago tp  ON tp.id_tipo_pago = m.id_tipo_pago;
GO

CREATE OR ALTER VIEW dbo.vw_bancos
AS
SELECT b.id_banco, b.nombre_banco, b.numero_cuenta, b.estado
FROM dbo.CP15_bancos b;
GO

CREATE OR ALTER VIEW dbo.vw_tipos_pago
AS
SELECT tp.id_tipo_pago,
       tp.nombre,
       tp.id_metodo_pago,
       mp.nombre AS metodo_pago,
       tp.id_banco,
       b.nombre_banco,
       mp.requiere_banco,
       tp.estado
FROM dbo.CP03_tipos_pago tp
JOIN dbo.CP06_metodos_pago mp ON mp.id_metodo_pago = tp.id_metodo_pago
LEFT JOIN dbo.CP15_bancos b   ON b.id_banco = tp.id_banco;
GO

CREATE OR ALTER VIEW dbo.vw_usuarios
AS
SELECT u.id_usuario,
       u.id_rol,
       r.nombre_rol AS rol,
       u.nombre_usuario,
       u.nombre_completo,
       u.correo,
       u.intentos_fallidos,
       u.ultimo_acceso,
       u.estado,
       u.fecha_registro
FROM dbo.CP12_usuarios u
JOIN dbo.CP11_roles r ON r.id_rol = u.id_rol;
GO

CREATE OR ALTER VIEW dbo.vw_roles
AS
SELECT id_rol, nombre_rol AS nombre, descripcion, nivel_acceso, puede_aprobar_pagos, estado
FROM dbo.CP11_roles;
GO

CREATE OR ALTER VIEW dbo.vw_programaciones
AS
SELECT pr.id_programacion,
       pr.id_proveedor,
       p.razon_social,
       pr.descripcion,
       pr.id_frecuencia,
       f.nombre AS frecuencia,
       pr.dia_ejecucion,
       pr.monto,
       pr.fecha_inicio,
       pr.fecha_fin,
       pr.proxima_ejecucion,
       pr.enviar_comprobante,
       pr.estado
FROM dbo.CP10_programacion_pagos pr
JOIN dbo.CP01_proveedores p ON p.id_proveedor = pr.id_proveedor
JOIN dbo.CP13_frecuencias f ON f.id_frecuencia = pr.id_frecuencia;
GO

CREATE OR ALTER VIEW dbo.vw_lotes
AS
SELECT l.id_lote,
       l.id_banco,
       b.nombre_banco,
       l.fecha_envio,
       l.cantidad_pagos,
       l.monto_total,
       l.id_estado,
       e.codigo AS estado,
       e.nombre AS estado_nombre
FROM dbo.CP16_lotes_transferencia l
JOIN dbo.CP15_bancos b  ON b.id_banco = l.id_banco
JOIN dbo.CP08_estados e ON e.id_estado = l.id_estado;
GO

CREATE OR ALTER VIEW dbo.vw_notificaciones
AS
SELECT n.id_notificacion,
       n.id_movimiento,
       n.id_proveedor,
       p.razon_social,
       n.correo_destino,
       n.asunto,
       n.fecha_envio,
       n.id_estado,
       e.codigo AS estado,
       e.nombre AS estado_nombre
FROM dbo.CP18_notificaciones n
JOIN dbo.CP01_proveedores p ON p.id_proveedor = n.id_proveedor
JOIN dbo.CP08_estados e     ON e.id_estado = n.id_estado;
GO

CREATE OR ALTER VIEW dbo.vw_catalogos_estado
AS
SELECT id_estado, ambito, codigo, nombre
FROM dbo.CP08_estados;
GO

CREATE OR ALTER VIEW dbo.vw_dashboard_resumen
AS
SELECT
    (SELECT COUNT(*) FROM dbo.CP04_cuentas_por_pagar c
     JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
     WHERE e.codigo IN ('PENDIENTE','PARCIAL','VENCIDA')) AS cuentas_abiertas,
    (SELECT ISNULL(SUM(c.saldo_pendiente), 0) FROM dbo.CP04_cuentas_por_pagar c
     JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
     WHERE e.codigo IN ('PENDIENTE','PARCIAL','VENCIDA')) AS saldo_pendiente,
    (SELECT COUNT(*) FROM dbo.CP04_cuentas_por_pagar c
     JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
     WHERE e.codigo = 'VENCIDA') AS cuentas_vencidas,
    (SELECT ISNULL(SUM(c.saldo_pendiente), 0) FROM dbo.CP04_cuentas_por_pagar c
     JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
     WHERE e.codigo = 'VENCIDA') AS saldo_vencido,
    (SELECT ISNULL(SUM(m.monto), 0) FROM dbo.CP07_movimientos m
     JOIN dbo.CP02_tipos_movimiento tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
     JOIN dbo.CP08_estados em          ON em.id_estado = m.id_estado
     WHERE tm.nombre = 'Pago' AND em.codigo = 'APROBADO'
       AND m.fecha_movimiento >= DATEFROMPARTS(YEAR(SYSDATETIME()), MONTH(SYSDATETIME()), 1)) AS pagos_mes;
GO

/* -------------------------------------------------------------------------------------
   Permisos de lectura: solo vistas, nunca tablas base.
   ------------------------------------------------------------------------------------- */
GRANT SELECT ON dbo.vw_proveedores             TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_cuentas_por_pagar       TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_cuentas_por_pagar_detalle TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_movimientos             TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_bancos                  TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_tipos_pago              TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_usuarios                TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_roles                   TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_programaciones          TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_lotes                   TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_notificaciones          TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_catalogos_estado        TO rol_cxp_operacion;
GRANT SELECT ON dbo.vw_dashboard_resumen       TO rol_cxp_operacion;
GO

GRANT SELECT ON dbo.vw_proveedores             TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_cuentas_por_pagar       TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_cuentas_por_pagar_detalle TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_movimientos             TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_bancos                  TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_tipos_pago              TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_usuarios                TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_roles                   TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_programaciones          TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_lotes                   TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_notificaciones          TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_catalogos_estado        TO rol_cxp_auditoria;
GRANT SELECT ON dbo.vw_dashboard_resumen       TO rol_cxp_auditoria;
GO
