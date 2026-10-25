SI /*Integrantes:
Emmanuel Jimenez Jimenez
Jhon Lopez Zuñiga
Joan Garcia Briceño
Pablo Gomez Villegas
*/ 

/* =====================================================================================
   Sistema de Gestión de Cuentas por Pagar - "Industria Global S.A."
*/
/* =====================================================================================
   1. BASE DE DATOS
   ===================================================================================== */
USE master;
GO
IF DB_ID(N'CuentasPorPagarDB') IS NULL
    CREATE DATABASE CuentasPorPagarDB;
GO
USE CuentasPorPagarDB;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* Limpieza (orden inverso a las dependencias) */
DROP TABLE IF EXISTS dbo.CP18_notificaciones;
DROP TABLE IF EXISTS dbo.CP07_movimientos;
DROP TABLE IF EXISTS dbo.CP16_lotes_transferencia;
DROP TABLE IF EXISTS dbo.CP10_programacion_pagos;
DROP TABLE IF EXISTS dbo.CP05_cuentas_por_pagar_detalle;
DROP TABLE IF EXISTS dbo.CP04_cuentas_por_pagar;
DROP TABLE IF EXISTS dbo.CP19_evaluaciones_proveedor;
DROP TABLE IF EXISTS dbo.CP03_tipos_pago;
DROP TABLE IF EXISTS dbo.CP02_tipos_movimiento;
DROP TABLE IF EXISTS dbo.CP12_usuarios;
DROP TABLE IF EXISTS dbo.CP11_roles;
DROP TABLE IF EXISTS dbo.CP14_sincronizacion_erp;
DROP TABLE IF EXISTS dbo.CP09_auditoria_acciones;
DROP TABLE IF EXISTS dbo.CP01_proveedores;
DROP TABLE IF EXISTS dbo.CP15_bancos;
DROP TABLE IF EXISTS dbo.CP13_frecuencias;
DROP TABLE IF EXISTS dbo.CP06_metodos_pago;
DROP TABLE IF EXISTS dbo.CP08_estados;
GO

/* =====================================================================================
   2. TABLAS
   ===================================================================================== */

/* ---------- Catálogos ---------- */

-- Catálogo compartido de estados. "ambito" indica a qué entidad pertenece cada estado.
CREATE TABLE dbo.CP08_estados (
    id_estado    INT IDENTITY(1,1) NOT NULL,
    ambito       VARCHAR(30)  NOT NULL,
    codigo       VARCHAR(20)  NOT NULL,
    nombre       VARCHAR(50)  NOT NULL,
    descripcion  VARCHAR(150) NULL,
    activo       BIT          NOT NULL CONSTRAINT DF_CP08_activo DEFAULT (1),
    CONSTRAINT PK_CP08_estados PRIMARY KEY (id_estado),
    CONSTRAINT UQ_CP08_ambito_codigo UNIQUE (ambito, codigo),
    CONSTRAINT CK_CP08_ambito CHECK (ambito IN ('CUENTA_PAGAR','MOVIMIENTO','LOTE','NOTIFICACION',
                                                'SINCRONIZACION','EFECTO_MOVIMIENTO'))
);
GO

CREATE TABLE dbo.CP06_metodos_pago (
    id_metodo_pago INT IDENTITY(1,1) NOT NULL,
    nombre         VARCHAR(50) NOT NULL,
    requiere_banco BIT         NOT NULL CONSTRAINT DF_CP06_requiere_banco DEFAULT (1),
    estado         BIT         NOT NULL CONSTRAINT DF_CP06_estado DEFAULT (1),
    CONSTRAINT PK_CP06_metodos_pago PRIMARY KEY (id_metodo_pago),
    CONSTRAINT UQ_CP06_nombre UNIQUE (nombre)
);
GO

-- Funciones que usan las restricciones CHECK de las tablas siguientes
CREATE OR ALTER FUNCTION dbo.fn_estado_es_de_ambito (@id_estado INT, @ambito VARCHAR(30))
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS (SELECT 1 FROM dbo.CP08_estados
                             WHERE id_estado = @id_estado AND ambito = @ambito)
                THEN 1 ELSE 0 END;
END;
GO

CREATE OR ALTER FUNCTION dbo.fn_metodo_requiere_banco (@id_metodo_pago INT)
RETURNS BIT
AS
BEGIN
    RETURN ISNULL((SELECT requiere_banco FROM dbo.CP06_metodos_pago
                   WHERE id_metodo_pago = @id_metodo_pago), 0);
END;
GO

CREATE TABLE dbo.CP13_frecuencias (
    id_frecuencia   INT IDENTITY(1,1) NOT NULL,
    nombre          VARCHAR(30) NOT NULL,
    dias_intervalo  SMALLINT    NOT NULL CONSTRAINT DF_CP13_dias DEFAULT (0),
    meses_intervalo TINYINT     NOT NULL CONSTRAINT DF_CP13_meses DEFAULT (0),
    estado          BIT         NOT NULL CONSTRAINT DF_CP13_estado DEFAULT (1),
    CONSTRAINT PK_CP13_frecuencias PRIMARY KEY (id_frecuencia),
    CONSTRAINT UQ_CP13_nombre UNIQUE (nombre),
    -- Se usa días o meses, no ambos
    CONSTRAINT CK_CP13_intervalo CHECK ((dias_intervalo > 0 AND meses_intervalo = 0)
                                     OR (dias_intervalo = 0 AND meses_intervalo > 0))
);
GO

CREATE TABLE dbo.CP15_bancos (
    id_banco      INT IDENTITY(1,1) NOT NULL,
    nombre_banco  VARCHAR(80) NOT NULL,
    numero_cuenta VARCHAR(34) NOT NULL,
    estado        BIT         NOT NULL CONSTRAINT DF_CP15_estado DEFAULT (1),
    CONSTRAINT PK_CP15_bancos PRIMARY KEY (id_banco),
    CONSTRAINT UQ_CP15_numero_cuenta UNIQUE (numero_cuenta)
);
GO

CREATE TABLE dbo.CP02_tipos_movimiento (
    id_tipo_movimiento INT IDENTITY(1,1) NOT NULL,
    nombre             VARCHAR(50) NOT NULL,
    id_efecto          INT         NOT NULL,
    estado             BIT         NOT NULL CONSTRAINT DF_CP02_estado DEFAULT (1),
    CONSTRAINT PK_CP02_tipos_movimiento PRIMARY KEY (id_tipo_movimiento),
    CONSTRAINT UQ_CP02_nombre UNIQUE (nombre),
    CONSTRAINT FK_CP02_efecto FOREIGN KEY (id_efecto) REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT CK_CP02_efecto_ambito CHECK (dbo.fn_estado_es_de_ambito(id_efecto, 'EFECTO_MOVIMIENTO') = 1)
);
GO

CREATE TABLE dbo.CP03_tipos_pago (
    id_tipo_pago   INT IDENTITY(1,1) NOT NULL,
    id_metodo_pago INT         NOT NULL,
    id_banco       INT         NULL,
    nombre         VARCHAR(50) NOT NULL,
    estado         BIT         NOT NULL CONSTRAINT DF_CP03_estado DEFAULT (1),
    CONSTRAINT PK_CP03_tipos_pago PRIMARY KEY (id_tipo_pago),
    CONSTRAINT UQ_CP03_nombre UNIQUE (nombre),
    CONSTRAINT FK_CP03_metodo FOREIGN KEY (id_metodo_pago) REFERENCES dbo.CP06_metodos_pago (id_metodo_pago),
    CONSTRAINT FK_CP03_banco  FOREIGN KEY (id_banco)       REFERENCES dbo.CP15_bancos (id_banco),
    -- Si el método requiere banco (transferencia, cheque...) el banco es obligatorio
    CONSTRAINT CK_CP03_banco_requerido CHECK (id_banco IS NOT NULL
                                           OR dbo.fn_metodo_requiere_banco(id_metodo_pago) = 0)
);
GO

/* ---------- Seguridad ---------- */

CREATE TABLE dbo.CP11_roles (
    id_rol              INT IDENTITY(1,1) NOT NULL,
    nombre_rol          VARCHAR(50)  NOT NULL,
    descripcion         VARCHAR(200) NOT NULL,
    nivel_acceso        TINYINT      NOT NULL,
    puede_aprobar_pagos BIT          NOT NULL CONSTRAINT DF_CP11_aprobar DEFAULT (0),
    estado              BIT          NOT NULL CONSTRAINT DF_CP11_estado DEFAULT (1),
    fecha_creacion      DATETIME2    NOT NULL CONSTRAINT DF_CP11_fecha DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_CP11_roles PRIMARY KEY (id_rol),
    CONSTRAINT UQ_CP11_nombre_rol UNIQUE (nombre_rol),
    CONSTRAINT CK_CP11_nivel CHECK (nivel_acceso BETWEEN 1 AND 5)
);
GO

CREATE TABLE dbo.CP12_usuarios (
    id_usuario        INT IDENTITY(1,1) NOT NULL,
    id_rol            INT            NOT NULL,
    nombre_usuario    VARCHAR(50)    NOT NULL,
    nombre_completo   VARCHAR(120)   NOT NULL,
    correo            VARCHAR(254)   NOT NULL,
    hash_contrasena   VARBINARY(64)  NOT NULL,
    salt              VARBINARY(64)  NOT NULL,
    intentos_fallidos INT            NOT NULL CONSTRAINT DF_CP12_intentos DEFAULT (0),
    ultimo_acceso     DATETIME2      NULL,
    estado            BIT            NOT NULL CONSTRAINT DF_CP12_estado DEFAULT (1),
    fecha_registro    DATETIME2      NOT NULL CONSTRAINT DF_CP12_fecha DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_CP12_usuarios PRIMARY KEY (id_usuario),
    CONSTRAINT UQ_CP12_nombre_usuario UNIQUE (nombre_usuario),
    CONSTRAINT UQ_CP12_correo UNIQUE (correo),
    CONSTRAINT FK_CP12_rol FOREIGN KEY (id_rol) REFERENCES dbo.CP11_roles (id_rol),
    CONSTRAINT CK_CP12_intentos CHECK (intentos_fallidos >= 0),
    CONSTRAINT CK_CP12_correo CHECK (correo LIKE '%_@_%._%')
);
GO

/* ---------- Proveedores ---------- */

CREATE TABLE dbo.CP01_proveedores (
    id_proveedor              INT IDENTITY(1,1) NOT NULL,
    identificacion_tributaria VARCHAR(30)  NOT NULL,
    razon_social              VARCHAR(150) NOT NULL,
    nombre_comercial          VARCHAR(150) NULL,
    pais                      VARCHAR(60)  NOT NULL,
    direccion                 VARCHAR(250) NOT NULL,
    telefono                  VARCHAR(30)  NOT NULL,
    correo                    VARCHAR(254) NOT NULL,
    contacto_nombre           VARCHAR(120) NULL,
    dias_credito              INT          NOT NULL CONSTRAINT DF_CP01_dias_credito DEFAULT (30),
    banco                     VARCHAR(80)  NOT NULL,
    cuenta_bancaria           VARCHAR(34)  NOT NULL,
    estado                    BIT          NOT NULL CONSTRAINT DF_CP01_estado DEFAULT (1),
    fecha_registro            DATETIME2    NOT NULL CONSTRAINT DF_CP01_fecha DEFAULT (SYSDATETIME()),
    codigo_erp                VARCHAR(50)  NULL,
    CONSTRAINT PK_CP01_proveedores PRIMARY KEY (id_proveedor),
    CONSTRAINT UQ_CP01_identificacion UNIQUE (identificacion_tributaria),
    CONSTRAINT CK_CP01_dias_credito CHECK (dias_credito BETWEEN 0 AND 365),
    CONSTRAINT CK_CP01_correo CHECK (correo LIKE '%_@_%._%')
);
GO

CREATE TABLE dbo.CP19_evaluaciones_proveedor (
    id_evaluacion    INT IDENTITY(1,1) NOT NULL,
    id_proveedor     INT          NOT NULL,
    fecha_evaluacion DATE         NOT NULL CONSTRAINT DF_CP19_fecha DEFAULT (CAST(SYSDATETIME() AS DATE)),
    calificacion     TINYINT      NOT NULL,
    comentario       VARCHAR(250) NULL,
    CONSTRAINT PK_CP19_evaluaciones PRIMARY KEY (id_evaluacion),
    CONSTRAINT FK_CP19_proveedor FOREIGN KEY (id_proveedor) REFERENCES dbo.CP01_proveedores (id_proveedor),
    CONSTRAINT CK_CP19_calificacion CHECK (calificacion BETWEEN 1 AND 5)
);
GO

/* ---------- Cuentas por pagar ---------- */

CREATE TABLE dbo.CP04_cuentas_por_pagar (
    id_cuenta_pagar           INT IDENTITY(1,1) NOT NULL,
    id_proveedor              INT           NOT NULL,
    numero_factura            VARCHAR(50)   NOT NULL,
    fecha_emision             DATE          NOT NULL,
    fecha_vencimiento         DATE          NOT NULL,
    dias_credito              INT           NOT NULL,
    pct_descuento_pronto_pago DECIMAL(5,2)  NOT NULL CONSTRAINT DF_CP04_pct DEFAULT (0),
    fecha_limite_descuento    DATE          NULL,
    subtotal                  DECIMAL(18,2) NOT NULL,
    monto_impuesto            DECIMAL(18,2) NOT NULL,
    monto_total               DECIMAL(18,2) NOT NULL,
    saldo_pendiente           DECIMAL(18,2) NOT NULL,
    id_estado                 INT           NOT NULL,
    fecha_registro            DATETIME2     NOT NULL CONSTRAINT DF_CP04_fecha DEFAULT (SYSDATETIME()),
    codigo_erp                VARCHAR(50)   NULL,
    CONSTRAINT PK_CP04_cuentas_por_pagar PRIMARY KEY (id_cuenta_pagar),
    CONSTRAINT UQ_CP04_proveedor_factura UNIQUE (id_proveedor, numero_factura),
    CONSTRAINT FK_CP04_proveedor FOREIGN KEY (id_proveedor) REFERENCES dbo.CP01_proveedores (id_proveedor),
    CONSTRAINT FK_CP04_estado    FOREIGN KEY (id_estado)    REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT CK_CP04_estado_ambito CHECK (dbo.fn_estado_es_de_ambito(id_estado, 'CUENTA_PAGAR') = 1),
    CONSTRAINT CK_CP04_fechas CHECK (fecha_vencimiento >= fecha_emision),
    CONSTRAINT CK_CP04_dias_credito CHECK (dias_credito >= 0),
    CONSTRAINT CK_CP04_pct CHECK (pct_descuento_pronto_pago BETWEEN 0 AND 100),
    -- Con descuento debe existir fecha límite, y esta cae entre la emisión y el vencimiento
    CONSTRAINT CK_CP04_descuento CHECK (
        (pct_descuento_pronto_pago = 0 AND fecha_limite_descuento IS NULL)
     OR (pct_descuento_pronto_pago > 0 AND fecha_limite_descuento BETWEEN fecha_emision AND fecha_vencimiento)),
    CONSTRAINT CK_CP04_montos CHECK (subtotal >= 0 AND monto_impuesto >= 0
                                 AND monto_total = subtotal + monto_impuesto),
    CONSTRAINT CK_CP04_saldo CHECK (saldo_pendiente >= 0)
);
GO

CREATE TABLE dbo.CP05_cuentas_por_pagar_detalle (
    id_detalle      INT IDENTITY(1,1) NOT NULL,
    id_cuenta_pagar INT           NOT NULL,
    numero_linea    INT           NOT NULL,
    descripcion     VARCHAR(250)  NOT NULL,
    cantidad        DECIMAL(18,4) NOT NULL,
    precio_unitario DECIMAL(18,4) NOT NULL,
    pct_impuesto    DECIMAL(5,2)  NOT NULL CONSTRAINT DF_CP05_pct DEFAULT (0),
    monto_impuesto  DECIMAL(18,2) NOT NULL,
    total_linea     DECIMAL(18,2) NOT NULL,
    CONSTRAINT PK_CP05_detalle PRIMARY KEY (id_detalle),
    CONSTRAINT UQ_CP05_cuenta_linea UNIQUE (id_cuenta_pagar, numero_linea),
    CONSTRAINT FK_CP05_cuenta FOREIGN KEY (id_cuenta_pagar)
        REFERENCES dbo.CP04_cuentas_por_pagar (id_cuenta_pagar) ON DELETE CASCADE,
    CONSTRAINT CK_CP05_valores CHECK (numero_linea > 0 AND cantidad > 0 AND precio_unitario >= 0
                                  AND pct_impuesto BETWEEN 0 AND 100 AND monto_impuesto >= 0),
    CONSTRAINT CK_CP05_total CHECK (total_linea = ROUND(cantidad * precio_unitario, 2) + monto_impuesto)
);
GO

/* ---------- Pagos ---------- */

CREATE TABLE dbo.CP10_programacion_pagos (
    id_programacion    INT IDENTITY(1,1) NOT NULL,
    id_proveedor       INT           NOT NULL,
    descripcion        VARCHAR(150)  NOT NULL,
    id_frecuencia      INT           NOT NULL,
    dia_ejecucion      TINYINT       NOT NULL,
    monto              DECIMAL(18,2) NOT NULL,
    fecha_inicio       DATE          NOT NULL,
    fecha_fin          DATE          NULL,
    proxima_ejecucion  DATE          NOT NULL,
    enviar_comprobante BIT           NOT NULL CONSTRAINT DF_CP10_enviar DEFAULT (1),
    estado             BIT           NOT NULL CONSTRAINT DF_CP10_estado DEFAULT (1),
    CONSTRAINT PK_CP10_programacion PRIMARY KEY (id_programacion),
    CONSTRAINT FK_CP10_proveedor  FOREIGN KEY (id_proveedor)  REFERENCES dbo.CP01_proveedores (id_proveedor),
    CONSTRAINT FK_CP10_frecuencia FOREIGN KEY (id_frecuencia) REFERENCES dbo.CP13_frecuencias (id_frecuencia),
    CONSTRAINT CK_CP10_dia CHECK (dia_ejecucion BETWEEN 1 AND 31),
    CONSTRAINT CK_CP10_monto CHECK (monto > 0),
    CONSTRAINT CK_CP10_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
    CONSTRAINT CK_CP10_proxima CHECK (proxima_ejecucion >= fecha_inicio)
);
GO

CREATE TABLE dbo.CP16_lotes_transferencia (
    id_lote        INT IDENTITY(1,1) NOT NULL,
    id_banco       INT           NOT NULL,
    fecha_envio    DATETIME2     NOT NULL CONSTRAINT DF_CP16_fecha DEFAULT (SYSDATETIME()),
    cantidad_pagos INT           NOT NULL CONSTRAINT DF_CP16_cantidad DEFAULT (0),
    monto_total    DECIMAL(18,2) NOT NULL CONSTRAINT DF_CP16_monto DEFAULT (0),
    id_estado      INT           NOT NULL,
    CONSTRAINT PK_CP16_lotes PRIMARY KEY (id_lote),
    CONSTRAINT FK_CP16_banco  FOREIGN KEY (id_banco)  REFERENCES dbo.CP15_bancos (id_banco),
    CONSTRAINT FK_CP16_estado FOREIGN KEY (id_estado) REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT CK_CP16_estado_ambito CHECK (dbo.fn_estado_es_de_ambito(id_estado, 'LOTE') = 1),
    CONSTRAINT CK_CP16_valores CHECK (cantidad_pagos >= 0 AND monto_total >= 0)
);
GO

CREATE TABLE dbo.CP07_movimientos (
    id_movimiento       INT IDENTITY(1,1) NOT NULL,
    id_cuenta_pagar     INT           NOT NULL,
    id_tipo_movimiento  INT           NOT NULL,
    numero_movimiento   INT           NOT NULL,
    id_tipo_pago        INT           NULL,
    id_lote             INT           NULL,
    id_programacion     INT           NULL,
    fecha_movimiento    DATE          NOT NULL,
    monto               DECIMAL(18,2) NOT NULL,
    referencia          VARCHAR(50)   NULL,
    descripcion         VARCHAR(150)  NULL,
    id_estado           INT           NOT NULL,
    conciliado          BIT           NOT NULL CONSTRAINT DF_CP07_conciliado DEFAULT (0),
    fecha_conciliacion  DATETIME2     NULL,
    id_usuario_registro INT           NOT NULL,
    fecha_registro      DATETIME2     NOT NULL CONSTRAINT DF_CP07_fecha DEFAULT (SYSDATETIME()),
    id_usuario_aprueba  INT           NULL,
    fecha_aprobacion    DATETIME2     NULL,
    codigo_erp          VARCHAR(50)   NULL,
    CONSTRAINT PK_CP07_movimientos PRIMARY KEY (id_movimiento),
    CONSTRAINT UQ_CP07_cuenta_numero UNIQUE (id_cuenta_pagar, numero_movimiento),
    CONSTRAINT FK_CP07_cuenta       FOREIGN KEY (id_cuenta_pagar)     REFERENCES dbo.CP04_cuentas_por_pagar (id_cuenta_pagar),
    CONSTRAINT FK_CP07_tipo_mov     FOREIGN KEY (id_tipo_movimiento)  REFERENCES dbo.CP02_tipos_movimiento (id_tipo_movimiento),
    CONSTRAINT FK_CP07_tipo_pago    FOREIGN KEY (id_tipo_pago)        REFERENCES dbo.CP03_tipos_pago (id_tipo_pago),
    CONSTRAINT FK_CP07_lote         FOREIGN KEY (id_lote)             REFERENCES dbo.CP16_lotes_transferencia (id_lote),
    CONSTRAINT FK_CP07_programacion FOREIGN KEY (id_programacion)     REFERENCES dbo.CP10_programacion_pagos (id_programacion),
    CONSTRAINT FK_CP07_estado       FOREIGN KEY (id_estado)           REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT FK_CP07_usuario_reg  FOREIGN KEY (id_usuario_registro) REFERENCES dbo.CP12_usuarios (id_usuario),
    CONSTRAINT FK_CP07_usuario_apr  FOREIGN KEY (id_usuario_aprueba)  REFERENCES dbo.CP12_usuarios (id_usuario),
    CONSTRAINT CK_CP07_estado_ambito CHECK (dbo.fn_estado_es_de_ambito(id_estado, 'MOVIMIENTO') = 1),
    CONSTRAINT CK_CP07_monto CHECK (monto > 0),
    CONSTRAINT CK_CP07_numero CHECK (numero_movimiento > 0),
    CONSTRAINT CK_CP07_aprobacion CHECK ((id_usuario_aprueba IS NULL AND fecha_aprobacion IS NULL)
                                      OR (id_usuario_aprueba IS NOT NULL AND fecha_aprobacion IS NOT NULL)),
    -- Segregación de funciones: quien registra no puede aprobar (R10)
    CONSTRAINT CK_CP07_segregacion CHECK (id_usuario_aprueba IS NULL OR id_usuario_aprueba <> id_usuario_registro),
    CONSTRAINT CK_CP07_conciliacion CHECK ((conciliado = 0 AND fecha_conciliacion IS NULL)
                                        OR (conciliado = 1 AND fecha_conciliacion IS NOT NULL))
);
GO

CREATE TABLE dbo.CP18_notificaciones (
    id_notificacion INT IDENTITY(1,1) NOT NULL,
    id_movimiento   INT          NULL,
    id_proveedor    INT          NOT NULL,
    correo_destino  VARCHAR(254) NOT NULL,
    asunto          VARCHAR(150) NOT NULL,
    fecha_envio     DATETIME2    NOT NULL CONSTRAINT DF_CP18_fecha DEFAULT (SYSDATETIME()),
    id_estado       INT          NOT NULL,
    CONSTRAINT PK_CP18_notificaciones PRIMARY KEY (id_notificacion),
    CONSTRAINT FK_CP18_movimiento FOREIGN KEY (id_movimiento) REFERENCES dbo.CP07_movimientos (id_movimiento),
    CONSTRAINT FK_CP18_proveedor  FOREIGN KEY (id_proveedor)  REFERENCES dbo.CP01_proveedores (id_proveedor),
    CONSTRAINT FK_CP18_estado     FOREIGN KEY (id_estado)     REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT CK_CP18_estado_ambito CHECK (dbo.fn_estado_es_de_ambito(id_estado, 'NOTIFICACION') = 1),
    CONSTRAINT CK_CP18_correo CHECK (correo_destino LIKE '%_@_%._%')
);
GO

/* ---------- Auditoría e integración ---------- */

-- id_usuario no lleva FK a propósito: el registro de auditoría se conserva aunque el usuario se elimine
CREATE TABLE dbo.CP09_auditoria_acciones (
    id_auditoria   BIGINT IDENTITY(1,1) NOT NULL,
    id_usuario     INT           NULL,
    tabla_afectada VARCHAR(60)   NOT NULL,
    id_registro    VARCHAR(50)   NOT NULL,
    accion         VARCHAR(20)   NOT NULL,
    valor_anterior NVARCHAR(MAX) NULL,
    valor_nuevo    NVARCHAR(MAX) NULL,
    direccion_ip   VARCHAR(45)   NOT NULL
        CONSTRAINT DF_CP09_ip DEFAULT (ISNULL(CAST(CONNECTIONPROPERTY('client_net_address') AS VARCHAR(45)), 'desconocida')),
    fecha_hora     DATETIME2     NOT NULL CONSTRAINT DF_CP09_fecha DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_CP09_auditoria PRIMARY KEY (id_auditoria),
    CONSTRAINT CK_CP09_accion CHECK (accion IN ('INSERT','UPDATE','DELETE','LOGIN','LOGIN_FALLIDO',
                                                'APROBAR','ANULAR','CONCILIAR','EJECUTAR','RESPALDO'))
);
GO

CREATE TABLE dbo.CP14_sincronizacion_erp (
    id_sincronizacion    INT IDENTITY(1,1) NOT NULL,
    tabla_origen         VARCHAR(60)  NOT NULL,
    id_registro          INT          NOT NULL,
    tipo_operacion       VARCHAR(20)  NOT NULL,
    fecha_sincronizacion DATETIME2    NOT NULL CONSTRAINT DF_CP14_fecha DEFAULT (SYSDATETIME()),
    id_estado            INT          NOT NULL,
    mensaje_error        VARCHAR(250) NULL,
    CONSTRAINT PK_CP14_sincronizacion PRIMARY KEY (id_sincronizacion),
    CONSTRAINT FK_CP14_estado FOREIGN KEY (id_estado) REFERENCES dbo.CP08_estados (id_estado),
    CONSTRAINT CK_CP14_estado_ambito CHECK (dbo.fn_estado_es_de_ambito(id_estado, 'SINCRONIZACION') = 1),
    CONSTRAINT CK_CP14_operacion CHECK (tipo_operacion IN ('INSERT','UPDATE','DELETE'))
);
GO

/* =====================================================================================
   3. ÍNDICES
   - Toda FK tiene índice (las que ya encabezan una PK/UNIQUE no lo repiten).
   - Índices filtrados para las consultas frecuentes: saldos abiertos, pendientes de
     conciliar, programaciones activas y códigos ERP.
   ===================================================================================== */

-- CP01 proveedores
CREATE UNIQUE INDEX UX_CP01_codigo_erp ON dbo.CP01_proveedores (codigo_erp) WHERE codigo_erp IS NOT NULL;
CREATE INDEX IX_CP01_razon_social ON dbo.CP01_proveedores (razon_social) INCLUDE (identificacion_tributaria, estado);

-- CP02 / CP03 catálogos
CREATE INDEX IX_CP02_efecto ON dbo.CP02_tipos_movimiento (id_efecto);
CREATE INDEX IX_CP03_metodo ON dbo.CP03_tipos_pago (id_metodo_pago);
CREATE INDEX IX_CP03_banco  ON dbo.CP03_tipos_pago (id_banco) WHERE id_banco IS NOT NULL;

-- CP04 cuentas por pagar (id_proveedor ya está cubierto por UQ_CP04_proveedor_factura)
CREATE INDEX IX_CP04_estado ON dbo.CP04_cuentas_por_pagar (id_estado);
CREATE INDEX IX_CP04_vencimiento_abiertas ON dbo.CP04_cuentas_por_pagar (fecha_vencimiento)
    INCLUDE (id_proveedor, saldo_pendiente, id_estado) WHERE saldo_pendiente > 0;
CREATE INDEX IX_CP04_descuento ON dbo.CP04_cuentas_por_pagar (fecha_limite_descuento)
    INCLUDE (id_proveedor, saldo_pendiente, pct_descuento_pronto_pago) WHERE fecha_limite_descuento IS NOT NULL;
CREATE UNIQUE INDEX UX_CP04_codigo_erp ON dbo.CP04_cuentas_por_pagar (codigo_erp) WHERE codigo_erp IS NOT NULL;

-- CP07 movimientos (id_cuenta_pagar ya está cubierto por UQ_CP07_cuenta_numero)
CREATE INDEX IX_CP07_tipo_mov     ON dbo.CP07_movimientos (id_tipo_movimiento);
CREATE INDEX IX_CP07_tipo_pago    ON dbo.CP07_movimientos (id_tipo_pago) WHERE id_tipo_pago IS NOT NULL;
CREATE INDEX IX_CP07_lote         ON dbo.CP07_movimientos (id_lote) WHERE id_lote IS NOT NULL;
CREATE INDEX IX_CP07_programacion ON dbo.CP07_movimientos (id_programacion) WHERE id_programacion IS NOT NULL;
CREATE INDEX IX_CP07_estado       ON dbo.CP07_movimientos (id_estado);
CREATE INDEX IX_CP07_usuario_reg  ON dbo.CP07_movimientos (id_usuario_registro);
CREATE INDEX IX_CP07_usuario_apr  ON dbo.CP07_movimientos (id_usuario_aprueba) WHERE id_usuario_aprueba IS NOT NULL;
CREATE INDEX IX_CP07_fecha        ON dbo.CP07_movimientos (fecha_movimiento)
    INCLUDE (id_cuenta_pagar, id_tipo_movimiento, monto, id_estado);
CREATE INDEX IX_CP07_por_conciliar ON dbo.CP07_movimientos (referencia, monto)
    INCLUDE (id_tipo_pago, fecha_movimiento, id_estado) WHERE conciliado = 0;
CREATE UNIQUE INDEX UX_CP07_codigo_erp ON dbo.CP07_movimientos (codigo_erp) WHERE codigo_erp IS NOT NULL;

-- CP09 auditoría
CREATE INDEX IX_CP09_fecha    ON dbo.CP09_auditoria_acciones (fecha_hora) INCLUDE (id_usuario, tabla_afectada, accion);
CREATE INDEX IX_CP09_registro ON dbo.CP09_auditoria_acciones (tabla_afectada, id_registro);
CREATE INDEX IX_CP09_usuario  ON dbo.CP09_auditoria_acciones (id_usuario, fecha_hora) WHERE id_usuario IS NOT NULL;

-- CP10 programación de pagos
CREATE INDEX IX_CP10_proveedor  ON dbo.CP10_programacion_pagos (id_proveedor);
CREATE INDEX IX_CP10_frecuencia ON dbo.CP10_programacion_pagos (id_frecuencia);
CREATE INDEX IX_CP10_proxima_activas ON dbo.CP10_programacion_pagos (proxima_ejecucion)
    INCLUDE (id_proveedor, monto, id_frecuencia, dia_ejecucion, fecha_fin) WHERE estado = 1;

-- CP12 usuarios
CREATE INDEX IX_CP12_rol ON dbo.CP12_usuarios (id_rol);

-- CP14 sincronización ERP
CREATE INDEX IX_CP14_estado   ON dbo.CP14_sincronizacion_erp (id_estado, fecha_sincronizacion);
CREATE INDEX IX_CP14_registro ON dbo.CP14_sincronizacion_erp (tabla_origen, id_registro);

-- CP16 lotes
CREATE INDEX IX_CP16_banco  ON dbo.CP16_lotes_transferencia (id_banco);
CREATE INDEX IX_CP16_estado ON dbo.CP16_lotes_transferencia (id_estado);

-- CP18 notificaciones
CREATE INDEX IX_CP18_movimiento ON dbo.CP18_notificaciones (id_movimiento) WHERE id_movimiento IS NOT NULL;
CREATE INDEX IX_CP18_proveedor  ON dbo.CP18_notificaciones (id_proveedor);
CREATE INDEX IX_CP18_estado     ON dbo.CP18_notificaciones (id_estado, fecha_envio);

-- CP19 evaluaciones
CREATE INDEX IX_CP19_proveedor ON dbo.CP19_evaluaciones_proveedor (id_proveedor, fecha_evaluacion) INCLUDE (calificacion);
GO

/* =====================================================================================
   4. DATOS INICIALES DE CATÁLOGOS
   ===================================================================================== */
INSERT INTO dbo.CP08_estados (ambito, codigo, nombre, descripcion) VALUES
 ('CUENTA_PAGAR',      'PENDIENTE',  'Pendiente',  'Sin pagos aplicados'),
 ('CUENTA_PAGAR',      'PARCIAL',    'Pago parcial','Tiene pagos aplicados y saldo pendiente'),
 ('CUENTA_PAGAR',      'VENCIDA',    'Vencida',    'Pasó la fecha de vencimiento con saldo pendiente'),
 ('CUENTA_PAGAR',      'PAGADA',     'Pagada',     'Saldo pendiente en cero'),
 ('CUENTA_PAGAR',      'ANULADA',    'Anulada',    'Cuenta anulada, no genera obligación'),
 ('MOVIMIENTO',        'REGISTRADO', 'Registrado', 'Pendiente de aprobación'),
 ('MOVIMIENTO',        'APROBADO',   'Aprobado',   'Aplicado al saldo de la cuenta'),
 ('MOVIMIENTO',        'ANULADO',    'Anulado',    'Sin efecto sobre el saldo'),
 ('LOTE',              'GENERADO',   'Generado',   'Lote armado, aún no enviado al banco'),
 ('LOTE',              'ENVIADO',    'Enviado',    'Enviado al banco'),
 ('LOTE',              'PROCESADO',  'Procesado',  'Confirmado por el banco'),
 ('LOTE',              'RECHAZADO',  'Rechazado',  'Rechazado por el banco'),
 ('NOTIFICACION',      'PENDIENTE',  'Pendiente',  'En cola de envío'),
 ('NOTIFICACION',      'ENVIADA',    'Enviada',    'Entregada al servidor de correo'),
 ('NOTIFICACION',      'FALLIDA',    'Fallida',    'No se pudo enviar'),
 ('SINCRONIZACION',    'PENDIENTE',  'Pendiente',  'En cola para enviar al ERP'),
 ('SINCRONIZACION',    'EXITOSA',    'Exitosa',    'Registrado en el ERP'),
 ('SINCRONIZACION',    'ERROR',      'Error',      'El ERP rechazó el registro'),
 ('EFECTO_MOVIMIENTO', 'DISMINUYE',  'Disminuye saldo', 'Pagos, descuentos y notas de crédito'),
 ('EFECTO_MOVIMIENTO', 'AUMENTA',    'Aumenta saldo',   'Notas de débito y cargos adicionales');

INSERT INTO dbo.CP06_metodos_pago (nombre, requiere_banco) VALUES
 ('Transferencia', 1), ('Cheque', 1), ('Tarjeta corporativa', 1), ('Efectivo', 0);

INSERT INTO dbo.CP13_frecuencias (nombre, dias_intervalo, meses_intervalo) VALUES
 ('Semanal', 7, 0), ('Quincenal', 15, 0), ('Mensual', 0, 1), ('Bimestral', 0, 2),
 ('Trimestral', 0, 3), ('Semestral', 0, 6), ('Anual', 0, 12);

INSERT INTO dbo.CP02_tipos_movimiento (nombre, id_efecto)
SELECT v.nombre, e.id_estado
FROM (VALUES ('Pago', 'DISMINUYE'), ('Descuento por pronto pago', 'DISMINUYE'),
             ('Nota de crédito', 'DISMINUYE'), ('Nota de débito', 'AUMENTA')) v (nombre, efecto)
JOIN dbo.CP08_estados e ON e.ambito = 'EFECTO_MOVIMIENTO' AND e.codigo = v.efecto;

INSERT INTO dbo.CP11_roles (nombre_rol, descripcion, nivel_acceso, puede_aprobar_pagos) VALUES
 ('Administrador', 'Administra usuarios, catálogos y parámetros', 5, 1),
 ('Tesorero',      'Registra y aprueba pagos, genera lotes',       4, 1),
 ('Contador',      'Registra cuentas por pagar y movimientos',     3, 0),
 ('Auditor',       'Consulta reportes y bitácora de auditoría',    2, 0),
 ('Consulta',      'Solo lectura de reportes',                     1, 0);
GO

/* =====================================================================================
   5. FUNCIONES
   ===================================================================================== */

-- Id de un estado a partir de su ámbito y código. Ej: dbo.fn_estado_id('CUENTA_PAGAR','PAGADA')
CREATE OR ALTER FUNCTION dbo.fn_estado_id (@ambito VARCHAR(30), @codigo VARCHAR(20))
RETURNS INT
AS
BEGIN
    RETURN (SELECT id_estado FROM dbo.CP08_estados WHERE ambito = @ambito AND codigo = @codigo);
END;
GO

-- Hash de contraseña con SHA2-512 y salt (64 bytes, igual al tamaño de la columna)
CREATE OR ALTER FUNCTION dbo.fn_hash_contrasena (@contrasena NVARCHAR(128), @salt VARBINARY(64))
RETURNS VARBINARY(64)
AS
BEGIN
    RETURN HASHBYTES('SHA2_512', @salt + CAST(@contrasena AS VARBINARY(256)));
END;
GO

-- 1 si el usuario existe y está activo
CREATE OR ALTER FUNCTION dbo.fn_usuario_activo (@id_usuario INT)
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS (SELECT 1 FROM dbo.CP12_usuarios WHERE id_usuario = @id_usuario AND estado = 1)
                THEN 1 ELSE 0 END;
END;
GO

-- 1 si el usuario está activo y su rol permite aprobar pagos
CREATE OR ALTER FUNCTION dbo.fn_usuario_puede_aprobar (@id_usuario INT)
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS (SELECT 1 FROM dbo.CP12_usuarios u
                             JOIN dbo.CP11_roles r ON r.id_rol = u.id_rol
                             WHERE u.id_usuario = @id_usuario AND u.estado = 1
                               AND r.estado = 1 AND r.puede_aprobar_pagos = 1)
                THEN 1 ELSE 0 END;
END;
GO

-- Mueve una fecha al día indicado de su mes; si el mes es más corto usa el último día
CREATE OR ALTER FUNCTION dbo.fn_ajustar_dia (@fecha DATE, @dia TINYINT)
RETURNS DATE
AS
BEGIN
    DECLARE @ultimo TINYINT = DAY(EOMONTH(@fecha));
    RETURN DATEFROMPARTS(YEAR(@fecha), MONTH(@fecha), IIF(@dia > @ultimo, @ultimo, @dia));
END;
GO

-- Fecha de la siguiente ejecución de una programación según su frecuencia
CREATE OR ALTER FUNCTION dbo.fn_siguiente_ejecucion (@fecha_actual DATE, @id_frecuencia INT, @dia_ejecucion TINYINT)
RETURNS DATE
AS
BEGIN
    DECLARE @dias SMALLINT, @meses TINYINT;
    SELECT @dias = dias_intervalo, @meses = meses_intervalo
    FROM dbo.CP13_frecuencias WHERE id_frecuencia = @id_frecuencia;

    RETURN CASE WHEN @meses > 0
                THEN dbo.fn_ajustar_dia(DATEADD(MONTH, @meses, @fecha_actual), @dia_ejecucion)
                ELSE DATEADD(DAY, @dias, @fecha_actual) END;
END;
GO

-- Días de atraso de una cuenta a una fecha de corte (0 si aún no vence)
CREATE OR ALTER FUNCTION dbo.fn_dias_vencido (@fecha_vencimiento DATE, @fecha_corte DATE)
RETURNS INT
AS
BEGIN
    RETURN IIF(@fecha_corte > @fecha_vencimiento, DATEDIFF(DAY, @fecha_vencimiento, @fecha_corte), 0);
END;
GO

-- Código de estado que le corresponde a una cuenta según su saldo y vencimiento
CREATE OR ALTER FUNCTION dbo.fn_estado_cuenta_codigo
    (@saldo DECIMAL(18,2), @monto_total DECIMAL(18,2), @fecha_vencimiento DATE, @fecha_corte DATE)
RETURNS VARCHAR(20)
AS
BEGIN
    RETURN CASE WHEN @saldo = 0                        THEN 'PAGADA'
                WHEN @fecha_corte > @fecha_vencimiento THEN 'VENCIDA'
                WHEN @saldo < @monto_total             THEN 'PARCIAL'
                ELSE 'PENDIENTE' END;
END;
GO

-- Monto de movimientos registrados (aún sin aprobar) que van a disminuir el saldo de la cuenta
CREATE OR ALTER FUNCTION dbo.fn_saldo_comprometido (@id_cuenta_pagar INT)
RETURNS DECIMAL(18,2)
AS
BEGIN
    RETURN ISNULL((
        SELECT SUM(m.monto)
        FROM dbo.CP07_movimientos m
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        WHERE m.id_cuenta_pagar = @id_cuenta_pagar
          AND m.id_estado = dbo.fn_estado_id('MOVIMIENTO', 'REGISTRADO')
          AND t.id_efecto = dbo.fn_estado_id('EFECTO_MOVIMIENTO', 'DISMINUYE')), 0);
END;
GO

-- Saldo que todavía se puede pagar o acreditar: saldo pendiente menos lo comprometido
CREATE OR ALTER FUNCTION dbo.fn_saldo_disponible (@id_cuenta_pagar INT)
RETURNS DECIMAL(18,2)
AS
BEGIN
    RETURN (SELECT saldo_pendiente FROM dbo.CP04_cuentas_por_pagar WHERE id_cuenta_pagar = @id_cuenta_pagar)
         - dbo.fn_saldo_comprometido(@id_cuenta_pagar);
END;
GO

-- Descuento por pronto pago disponible si se cancela el saldo en @fecha_pago (0 si ya no aplica)
CREATE OR ALTER FUNCTION dbo.fn_descuento_pronto_pago (@id_cuenta_pagar INT, @fecha_pago DATE)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @descuento DECIMAL(18,2) = 0;
    SELECT @descuento = ROUND(dbo.fn_saldo_disponible(c.id_cuenta_pagar) * c.pct_descuento_pronto_pago / 100, 2)
    FROM dbo.CP04_cuentas_por_pagar c
    WHERE c.id_cuenta_pagar = @id_cuenta_pagar
      AND c.pct_descuento_pronto_pago > 0
      AND @fecha_pago <= c.fecha_limite_descuento;
    RETURN ISNULL(@descuento, 0);
END;
GO

-- Antigüedad de saldos a una fecha de corte (cuentas con saldo, sin anuladas)
CREATE OR ALTER FUNCTION dbo.fn_antiguedad_saldos (@fecha_corte DATE)
RETURNS TABLE
AS
RETURN
    SELECT c.id_cuenta_pagar, c.id_proveedor, p.razon_social, c.numero_factura,
           c.fecha_emision, c.fecha_vencimiento, c.saldo_pendiente, d.dias_vencido,
           CASE WHEN d.dias_vencido = 0  THEN 'Por vencer'
                WHEN d.dias_vencido <= 30 THEN '1-30 días'
                WHEN d.dias_vencido <= 60 THEN '31-60 días'
                WHEN d.dias_vencido <= 90 THEN '61-90 días'
                ELSE 'Más de 90 días' END AS rango
    FROM dbo.CP04_cuentas_por_pagar c
    JOIN dbo.CP01_proveedores p ON p.id_proveedor = c.id_proveedor
    JOIN dbo.CP08_estados e     ON e.id_estado = c.id_estado
    CROSS APPLY (SELECT dbo.fn_dias_vencido(c.fecha_vencimiento, @fecha_corte) AS dias_vencido) d
    WHERE c.saldo_pendiente > 0 AND e.codigo <> 'ANULADA';
GO

-- Historial de movimientos aprobados de un proveedor (R7)
CREATE OR ALTER FUNCTION dbo.fn_historial_pagos_proveedor (@id_proveedor INT)
RETURNS TABLE
AS
RETURN
    SELECT m.id_movimiento, c.numero_factura, m.numero_movimiento, m.fecha_movimiento,
           t.nombre AS tipo_movimiento, ef.codigo AS efecto, m.monto,
           tp.nombre AS tipo_pago, mp.nombre AS metodo_pago, m.referencia,
           m.conciliado, m.fecha_aprobacion
    FROM dbo.CP07_movimientos m
    JOIN dbo.CP04_cuentas_por_pagar c  ON c.id_cuenta_pagar = m.id_cuenta_pagar
    JOIN dbo.CP02_tipos_movimiento t   ON t.id_tipo_movimiento = m.id_tipo_movimiento
    JOIN dbo.CP08_estados ef           ON ef.id_estado = t.id_efecto
    JOIN dbo.CP08_estados em           ON em.id_estado = m.id_estado
    LEFT JOIN dbo.CP03_tipos_pago tp   ON tp.id_tipo_pago = m.id_tipo_pago
    LEFT JOIN dbo.CP06_metodos_pago mp ON mp.id_metodo_pago = tp.id_metodo_pago
    WHERE c.id_proveedor = @id_proveedor AND em.codigo = 'APROBADO';
GO

-- Salidas de efectivo proyectadas entre dos fechas: saldos por vencer + pagos programados (R3)
CREATE OR ALTER FUNCTION dbo.fn_flujo_caja_proyectado (@desde DATE, @hasta DATE)
RETURNS @flujo TABLE (
    fecha        DATE,
    origen       VARCHAR(20),
    id_proveedor INT,
    referencia   VARCHAR(60),
    monto        DECIMAL(18,2)
)
AS
BEGIN
    -- Cuentas con saldo: las ya vencidas se proyectan en la primera fecha del rango
    INSERT INTO @flujo (fecha, origen, id_proveedor, referencia, monto)
    SELECT IIF(c.fecha_vencimiento < @desde, @desde, c.fecha_vencimiento),
           IIF(c.fecha_vencimiento < @desde, 'Cuenta vencida', 'Cuenta por pagar'),
           c.id_proveedor, c.numero_factura, c.saldo_pendiente
    FROM dbo.CP04_cuentas_por_pagar c
    JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
    WHERE c.saldo_pendiente > 0 AND e.codigo <> 'ANULADA' AND c.fecha_vencimiento <= @hasta;

    -- Pagos programados: se recorre cada ejecución futura dentro del rango
    DECLARE @prog TABLE (id_programacion INT, id_proveedor INT, id_frecuencia INT, dia TINYINT,
                         monto DECIMAL(18,2), proxima DATE, fecha_fin DATE, descripcion VARCHAR(150));
    INSERT INTO @prog
    SELECT id_programacion, id_proveedor, id_frecuencia, dia_ejecucion, monto,
           proxima_ejecucion, fecha_fin, descripcion
    FROM dbo.CP10_programacion_pagos
    WHERE estado = 1 AND proxima_ejecucion <= @hasta;

    DECLARE @id INT, @fecha DATE;
    WHILE EXISTS (SELECT 1 FROM @prog)
    BEGIN
        SELECT TOP (1) @id = id_programacion, @fecha = proxima FROM @prog ORDER BY id_programacion;

        WHILE @fecha <= @hasta
              AND @fecha <= ISNULL((SELECT fecha_fin FROM @prog WHERE id_programacion = @id), '99991231')
        BEGIN
            IF @fecha >= @desde
                INSERT INTO @flujo (fecha, origen, id_proveedor, referencia, monto)
                SELECT @fecha, 'Pago programado', id_proveedor, LEFT(descripcion, 60), monto
                FROM @prog WHERE id_programacion = @id;

            SELECT @fecha = dbo.fn_siguiente_ejecucion(@fecha, id_frecuencia, dia)
            FROM @prog WHERE id_programacion = @id;
        END;

        DELETE FROM @prog WHERE id_programacion = @id;
    END;
    RETURN;
END;
GO

/* =====================================================================================
   6. TRIGGERS DE AUDITORÍA (R5) Y COLA DE SINCRONIZACIÓN ERP (R3)
   El usuario se toma de SESSION_CONTEXT('id_usuario'), que fijan los procedimientos.
   ===================================================================================== */

CREATE OR ALTER TRIGGER dbo.trg_CP01_auditoria ON dbo.CP01_proveedores
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP01_proveedores',
           CAST(COALESCE(i.id_proveedor, d.id_proveedor) AS VARCHAR(50)),
           CASE WHEN d.id_proveedor IS NULL THEN 'INSERT' WHEN i.id_proveedor IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.* FROM deleted  x WHERE x.id_proveedor = d.id_proveedor FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.* FROM inserted x WHERE x.id_proveedor = i.id_proveedor FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_proveedor = i.id_proveedor;

    INSERT INTO dbo.CP14_sincronizacion_erp (tabla_origen, id_registro, tipo_operacion, id_estado)
    SELECT 'CP01_proveedores', COALESCE(i.id_proveedor, d.id_proveedor),
           CASE WHEN d.id_proveedor IS NULL THEN 'INSERT' WHEN i.id_proveedor IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           dbo.fn_estado_id('SINCRONIZACION', 'PENDIENTE')
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_proveedor = i.id_proveedor;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_CP04_auditoria ON dbo.CP04_cuentas_por_pagar
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP04_cuentas_por_pagar',
           CAST(COALESCE(i.id_cuenta_pagar, d.id_cuenta_pagar) AS VARCHAR(50)),
           CASE WHEN d.id_cuenta_pagar IS NULL THEN 'INSERT' WHEN i.id_cuenta_pagar IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.* FROM deleted  x WHERE x.id_cuenta_pagar = d.id_cuenta_pagar FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.* FROM inserted x WHERE x.id_cuenta_pagar = i.id_cuenta_pagar FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_cuenta_pagar = i.id_cuenta_pagar;

    INSERT INTO dbo.CP14_sincronizacion_erp (tabla_origen, id_registro, tipo_operacion, id_estado)
    SELECT 'CP04_cuentas_por_pagar', COALESCE(i.id_cuenta_pagar, d.id_cuenta_pagar),
           CASE WHEN d.id_cuenta_pagar IS NULL THEN 'INSERT' WHEN i.id_cuenta_pagar IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           dbo.fn_estado_id('SINCRONIZACION', 'PENDIENTE')
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_cuenta_pagar = i.id_cuenta_pagar;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_CP07_auditoria ON dbo.CP07_movimientos
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP07_movimientos',
           CAST(COALESCE(i.id_movimiento, d.id_movimiento) AS VARCHAR(50)),
           CASE WHEN d.id_movimiento IS NULL THEN 'INSERT' WHEN i.id_movimiento IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.* FROM deleted  x WHERE x.id_movimiento = d.id_movimiento FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.* FROM inserted x WHERE x.id_movimiento = i.id_movimiento FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_movimiento = i.id_movimiento;

    INSERT INTO dbo.CP14_sincronizacion_erp (tabla_origen, id_registro, tipo_operacion, id_estado)
    SELECT 'CP07_movimientos', COALESCE(i.id_movimiento, d.id_movimiento),
           CASE WHEN d.id_movimiento IS NULL THEN 'INSERT' WHEN i.id_movimiento IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           dbo.fn_estado_id('SINCRONIZACION', 'PENDIENTE')
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_movimiento = i.id_movimiento;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_CP10_auditoria ON dbo.CP10_programacion_pagos
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP10_programacion_pagos',
           CAST(COALESCE(i.id_programacion, d.id_programacion) AS VARCHAR(50)),
           CASE WHEN d.id_programacion IS NULL THEN 'INSERT' WHEN i.id_programacion IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.* FROM deleted  x WHERE x.id_programacion = d.id_programacion FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.* FROM inserted x WHERE x.id_programacion = i.id_programacion FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_programacion = i.id_programacion;
END;
GO

-- Usuarios: no se guarda el hash ni el salt en la bitácora
CREATE OR ALTER TRIGGER dbo.trg_CP12_auditoria ON dbo.CP12_usuarios
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP12_usuarios',
           CAST(COALESCE(i.id_usuario, d.id_usuario) AS VARCHAR(50)),
           CASE WHEN d.id_usuario IS NULL THEN 'INSERT' WHEN i.id_usuario IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.id_usuario, x.id_rol, x.nombre_usuario, x.nombre_completo, x.correo,
                   x.intentos_fallidos, x.ultimo_acceso, x.estado, x.fecha_registro
            FROM deleted x WHERE x.id_usuario = d.id_usuario FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.id_usuario, x.id_rol, x.nombre_usuario, x.nombre_completo, x.correo,
                   x.intentos_fallidos, x.ultimo_acceso, x.estado, x.fecha_registro
            FROM inserted x WHERE x.id_usuario = i.id_usuario FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_usuario = i.id_usuario;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_CP16_auditoria ON dbo.CP16_lotes_transferencia
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted) RETURN;

    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    SELECT CAST(SESSION_CONTEXT(N'id_usuario') AS INT), 'CP16_lotes_transferencia',
           CAST(COALESCE(i.id_lote, d.id_lote) AS VARCHAR(50)),
           CASE WHEN d.id_lote IS NULL THEN 'INSERT' WHEN i.id_lote IS NULL THEN 'DELETE' ELSE 'UPDATE' END,
           (SELECT x.* FROM deleted  x WHERE x.id_lote = d.id_lote FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
           (SELECT x.* FROM inserted x WHERE x.id_lote = i.id_lote FOR JSON PATH, WITHOUT_ARRAY_WRAPPER)
    FROM inserted i FULL OUTER JOIN deleted d ON d.id_lote = i.id_lote;
END;
GO

-- La bitácora no se puede modificar ni borrar
CREATE OR ALTER TRIGGER dbo.trg_CP09_inmutable ON dbo.CP09_auditoria_acciones
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    THROW 50090, 'La bitácora de auditoría no se puede modificar ni eliminar.', 1;
END;
GO

/* =====================================================================================
   7. PROCEDIMIENTOS ALMACENADOS
   Convenciones:
   - Todos los que modifican datos reciben @id_usuario, lo validan y lo guardan en
     SESSION_CONTEXT para que los triggers de auditoría lo registren.
   - Manejo de transacciones con punto de guardado: funcionan solos o llamados desde
     otro procedimiento que ya abrió una transacción.
   - Los errores de negocio usan THROW con códigos 50000-50999.
   ===================================================================================== */

/* ---------- 7.0 Auxiliares ---------- */

CREATE OR ALTER PROCEDURE dbo.sp_contexto_usuario
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;
    IF dbo.fn_usuario_activo(@id_usuario) = 0
        THROW 50001, 'El usuario no existe o está inactivo.', 1;
    EXEC sys.sp_set_session_context @key = N'id_usuario', @value = @id_usuario;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_auditoria_registrar
    @id_usuario     INT,
    @tabla_afectada VARCHAR(60),
    @id_registro    VARCHAR(50),
    @accion         VARCHAR(20),
    @valor_anterior NVARCHAR(MAX) = NULL,
    @valor_nuevo    NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO dbo.CP09_auditoria_acciones (id_usuario, tabla_afectada, id_registro, accion, valor_anterior, valor_nuevo)
    VALUES (@id_usuario, @tabla_afectada, @id_registro, @accion, @valor_anterior, @valor_nuevo);
END;
GO

/* ---------- 7.1 Seguridad y usuarios ---------- */

CREATE OR ALTER PROCEDURE dbo.sp_usuario_crear
    @id_rol          INT,
    @nombre_usuario  VARCHAR(50),
    @nombre_completo VARCHAR(120),
    @correo          VARCHAR(254),
    @contrasena      NVARCHAR(128),
    @id_usuario_admin INT = NULL,       -- NULL solo para crear el primer usuario del sistema
    @id_usuario      INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    IF @id_usuario_admin IS NOT NULL
    BEGIN
        EXEC dbo.sp_contexto_usuario @id_usuario_admin;
        IF NOT EXISTS (SELECT 1 FROM dbo.CP12_usuarios u JOIN dbo.CP11_roles r ON r.id_rol = u.id_rol
                       WHERE u.id_usuario = @id_usuario_admin AND r.nivel_acceso = 5)
            THROW 50006, 'Solo un administrador puede crear usuarios.', 1;
    END
    ELSE IF EXISTS (SELECT 1 FROM dbo.CP12_usuarios)
        THROW 50002, 'Debe indicar el administrador que crea el usuario.', 1;

    IF LEN(@contrasena) < 8
        THROW 50003, 'La contraseña debe tener al menos 8 caracteres.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.CP11_roles WHERE id_rol = @id_rol AND estado = 1)
        THROW 50004, 'El rol no existe o está inactivo.', 1;

    DECLARE @salt VARBINARY(64) = CRYPT_GEN_RANDOM(32);
    INSERT INTO dbo.CP12_usuarios (id_rol, nombre_usuario, nombre_completo, correo, hash_contrasena, salt)
    VALUES (@id_rol, @nombre_usuario, @nombre_completo, @correo, dbo.fn_hash_contrasena(@contrasena, @salt), @salt);
    SET @id_usuario = SCOPE_IDENTITY();
END;
GO

-- Resultado: OK, INVALIDO o BLOQUEADO. Bloquea al usuario después de 5 intentos fallidos.
CREATE OR ALTER PROCEDURE dbo.sp_usuario_autenticar
    @nombre_usuario VARCHAR(50),
    @contrasena     NVARCHAR(128),
    @id_usuario     INT OUTPUT,
    @resultado      VARCHAR(20) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @hash VARBINARY(64), @salt VARBINARY(64), @estado BIT, @intentos INT;
    SET @id_usuario = NULL;

    SELECT @id_usuario = id_usuario, @hash = hash_contrasena, @salt = salt,
           @estado = estado, @intentos = intentos_fallidos
    FROM dbo.CP12_usuarios WHERE nombre_usuario = @nombre_usuario;

    IF @id_usuario IS NULL
    BEGIN
        SET @resultado = 'INVALIDO';
        RETURN;
    END;

    EXEC sys.sp_set_session_context @key = N'id_usuario', @value = @id_usuario;

    IF @estado = 0
    BEGIN
        SELECT @resultado = 'BLOQUEADO', @id_usuario = NULL;
        RETURN;
    END;

    IF dbo.fn_hash_contrasena(@contrasena, @salt) = @hash
    BEGIN
        UPDATE dbo.CP12_usuarios SET intentos_fallidos = 0, ultimo_acceso = SYSDATETIME()
        WHERE id_usuario = @id_usuario;
        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP12_usuarios', @id_usuario, 'LOGIN';
        SET @resultado = 'OK';
    END
    ELSE
    BEGIN
        UPDATE dbo.CP12_usuarios
        SET intentos_fallidos = intentos_fallidos + 1,
            estado = IIF(intentos_fallidos + 1 >= 5, 0, estado)
        WHERE id_usuario = @id_usuario;
        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP12_usuarios', @id_usuario, 'LOGIN_FALLIDO';
        SELECT @resultado = IIF(@intentos + 1 >= 5, 'BLOQUEADO', 'INVALIDO'), @id_usuario = NULL;
    END;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_usuario_cambiar_contrasena
    @id_usuario        INT,
    @contrasena_actual NVARCHAR(128),
    @contrasena_nueva  NVARCHAR(128)
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF LEN(@contrasena_nueva) < 8
        THROW 50003, 'La contraseña debe tener al menos 8 caracteres.', 1;
    IF NOT EXISTS (SELECT 1 FROM dbo.CP12_usuarios
                   WHERE id_usuario = @id_usuario
                     AND hash_contrasena = dbo.fn_hash_contrasena(@contrasena_actual, salt))
        THROW 50005, 'La contraseña actual no es correcta.', 1;

    DECLARE @salt VARBINARY(64) = CRYPT_GEN_RANDOM(32);
    UPDATE dbo.CP12_usuarios
    SET salt = @salt, hash_contrasena = dbo.fn_hash_contrasena(@contrasena_nueva, @salt), intentos_fallidos = 0
    WHERE id_usuario = @id_usuario;
END;
GO

/* ---------- 7.2 Proveedores ---------- */

-- Inserta si @id_proveedor es NULL; si no, actualiza
CREATE OR ALTER PROCEDURE dbo.sp_proveedor_guardar
    @id_proveedor              INT OUTPUT,
    @identificacion_tributaria VARCHAR(30),
    @razon_social              VARCHAR(150),
    @nombre_comercial          VARCHAR(150) = NULL,
    @pais                      VARCHAR(60),
    @direccion                 VARCHAR(250),
    @telefono                  VARCHAR(30),
    @correo                    VARCHAR(254),
    @contacto_nombre           VARCHAR(120) = NULL,
    @dias_credito              INT = 30,
    @banco                     VARCHAR(80),
    @cuenta_bancaria           VARCHAR(34),
    @estado                    BIT = 1,
    @codigo_erp                VARCHAR(50) = NULL,
    @id_usuario                INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    IF @id_proveedor IS NULL
    BEGIN
        INSERT INTO dbo.CP01_proveedores (identificacion_tributaria, razon_social, nombre_comercial, pais, direccion,
                                          telefono, correo, contacto_nombre, dias_credito, banco, cuenta_bancaria,
                                          estado, codigo_erp)
        VALUES (@identificacion_tributaria, @razon_social, @nombre_comercial, @pais, @direccion, @telefono, @correo,
                @contacto_nombre, @dias_credito, @banco, @cuenta_bancaria, @estado, @codigo_erp);
        SET @id_proveedor = SCOPE_IDENTITY();
    END
    ELSE
    BEGIN
        UPDATE dbo.CP01_proveedores
        SET identificacion_tributaria = @identificacion_tributaria, razon_social = @razon_social,
            nombre_comercial = @nombre_comercial, pais = @pais, direccion = @direccion, telefono = @telefono,
            correo = @correo, contacto_nombre = @contacto_nombre, dias_credito = @dias_credito, banco = @banco,
            cuenta_bancaria = @cuenta_bancaria, estado = @estado, codigo_erp = @codigo_erp
        WHERE id_proveedor = @id_proveedor;
        IF @@ROWCOUNT = 0
            THROW 50010, 'El proveedor no existe.', 1;
    END;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_proveedor_evaluar
    @id_proveedor INT,
    @calificacion TINYINT,
    @comentario   VARCHAR(250) = NULL,
    @fecha        DATE = NULL,
    @id_usuario   INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    INSERT INTO dbo.CP19_evaluaciones_proveedor (id_proveedor, fecha_evaluacion, calificacion, comentario)
    VALUES (@id_proveedor, ISNULL(@fecha, CAST(SYSDATETIME() AS DATE)), @calificacion, @comentario);
END;
GO

/* ---------- 7.3 Cuentas por pagar ---------- */

/* Registra la cuenta por pagar con su detalle en una sola transacción.
   @detalle es un arreglo JSON:
   [{"descripcion":"Servicio de soporte","cantidad":2,"precio_unitario":150.00,"pct_impuesto":13}]
   Los totales se calculan a partir del detalle. Si no se envía @dias_credito se usa el del proveedor. */
CREATE OR ALTER PROCEDURE dbo.sp_cuenta_pagar_registrar
    @id_proveedor              INT,
    @numero_factura            VARCHAR(50),
    @fecha_emision             DATE,
    @detalle                   NVARCHAR(MAX),
    @dias_credito              INT = NULL,
    @pct_descuento_pronto_pago DECIMAL(5,2) = 0,
    @dias_descuento            INT = NULL,
    @codigo_erp                VARCHAR(50) = NULL,
    @id_usuario                INT,
    @id_cuenta_pagar           INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    IF NOT EXISTS (SELECT 1 FROM dbo.CP01_proveedores WHERE id_proveedor = @id_proveedor AND estado = 1)
        THROW 50020, 'El proveedor no existe o está inactivo.', 1;
    IF ISJSON(@detalle) = 0 OR NOT EXISTS (SELECT 1 FROM OPENJSON(@detalle))
        THROW 50021, 'El detalle debe ser un arreglo JSON con al menos una línea.', 1;
    IF @pct_descuento_pronto_pago > 0 AND @dias_descuento IS NULL
        THROW 50022, 'Si hay descuento por pronto pago debe indicar los días para aprovecharlo.', 1;

    SELECT @dias_credito = ISNULL(@dias_credito, dias_credito)
    FROM dbo.CP01_proveedores WHERE id_proveedor = @id_proveedor;
    IF @dias_descuento > @dias_credito
        THROW 50023, 'Los días para el descuento no pueden superar los días de crédito.', 1;

    DECLARE @lineas TABLE (numero_linea INT, descripcion VARCHAR(250), cantidad DECIMAL(18,4),
                           precio_unitario DECIMAL(18,4), pct_impuesto DECIMAL(5,2),
                           subtotal DECIMAL(18,2), monto_impuesto DECIMAL(18,2));
    INSERT INTO @lineas
    SELECT ROW_NUMBER() OVER (ORDER BY CAST(j.[key] AS INT)), v.descripcion, v.cantidad, v.precio_unitario,
           ISNULL(v.pct_impuesto, 0),
           ROUND(v.cantidad * v.precio_unitario, 2),
           ROUND(ROUND(v.cantidad * v.precio_unitario, 2) * ISNULL(v.pct_impuesto, 0) / 100, 2)
    FROM OPENJSON(@detalle) j
    CROSS APPLY OPENJSON(j.[value]) WITH (descripcion VARCHAR(250), cantidad DECIMAL(18,4),
                                          precio_unitario DECIMAL(18,4), pct_impuesto DECIMAL(5,2)) v;

    DECLARE @subtotal DECIMAL(18,2), @impuesto DECIMAL(18,2), @vence DATE = DATEADD(DAY, @dias_credito, @fecha_emision);
    SELECT @subtotal = SUM(subtotal), @impuesto = SUM(monto_impuesto) FROM @lineas;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_cuenta_registrar;

        INSERT INTO dbo.CP04_cuentas_por_pagar (id_proveedor, numero_factura, fecha_emision, fecha_vencimiento,
                    dias_credito, pct_descuento_pronto_pago, fecha_limite_descuento, subtotal, monto_impuesto,
                    monto_total, saldo_pendiente, id_estado, codigo_erp)
        VALUES (@id_proveedor, @numero_factura, @fecha_emision, @vence, @dias_credito, @pct_descuento_pronto_pago,
                IIF(@pct_descuento_pronto_pago > 0, DATEADD(DAY, @dias_descuento, @fecha_emision), NULL),
                @subtotal, @impuesto, @subtotal + @impuesto, @subtotal + @impuesto,
                dbo.fn_estado_id('CUENTA_PAGAR',
                    dbo.fn_estado_cuenta_codigo(@subtotal + @impuesto, @subtotal + @impuesto, @vence, CAST(SYSDATETIME() AS DATE))),
                @codigo_erp);
        SET @id_cuenta_pagar = SCOPE_IDENTITY();

        INSERT INTO dbo.CP05_cuentas_por_pagar_detalle (id_cuenta_pagar, numero_linea, descripcion, cantidad,
                    precio_unitario, pct_impuesto, monto_impuesto, total_linea)
        SELECT @id_cuenta_pagar, numero_linea, descripcion, cantidad, precio_unitario, pct_impuesto,
               monto_impuesto, subtotal + monto_impuesto
        FROM @lineas;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_cuenta_registrar;
        THROW;
    END CATCH;
END;
GO

-- Solo se anula si no tiene movimientos aprobados
CREATE OR ALTER PROCEDURE dbo.sp_cuenta_pagar_anular
    @id_cuenta_pagar INT,
    @motivo          VARCHAR(150),
    @id_usuario      INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF dbo.fn_usuario_puede_aprobar(@id_usuario) = 0
        THROW 50030, 'El rol del usuario no permite anular cuentas por pagar.', 1;
    IF EXISTS (SELECT 1 FROM dbo.CP07_movimientos
               WHERE id_cuenta_pagar = @id_cuenta_pagar AND id_estado = dbo.fn_estado_id('MOVIMIENTO','APROBADO'))
        THROW 50031, 'La cuenta tiene movimientos aprobados; anule primero esos movimientos.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_cuenta_anular;

        UPDATE dbo.CP07_movimientos SET id_estado = dbo.fn_estado_id('MOVIMIENTO','ANULADO')
        WHERE id_cuenta_pagar = @id_cuenta_pagar AND id_estado = dbo.fn_estado_id('MOVIMIENTO','REGISTRADO');

        UPDATE dbo.CP04_cuentas_por_pagar
        SET id_estado = dbo.fn_estado_id('CUENTA_PAGAR','ANULADA'), saldo_pendiente = 0
        WHERE id_cuenta_pagar = @id_cuenta_pagar;
        IF @@ROWCOUNT = 0
            THROW 50032, 'La cuenta por pagar no existe.', 1;

        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP04_cuentas_por_pagar', @id_cuenta_pagar, 'ANULAR',
             NULL, @motivo;
        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_cuenta_anular;
        THROW;
    END CATCH;
END;
GO

-- Marca como VENCIDA toda cuenta con saldo cuyo vencimiento ya pasó (tarea diaria)
CREATE OR ALTER PROCEDURE dbo.sp_cuentas_actualizar_vencidas
    @fecha_corte DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    UPDATE c
    SET id_estado = dbo.fn_estado_id('CUENTA_PAGAR', 'VENCIDA')
    FROM dbo.CP04_cuentas_por_pagar c
    JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
    WHERE e.codigo IN ('PENDIENTE', 'PARCIAL') AND c.saldo_pendiente > 0 AND c.fecha_vencimiento < @fecha_corte;

    SELECT @@ROWCOUNT AS cuentas_vencidas;
END;
GO

/* ---------- 7.4 Movimientos y pagos ---------- */

/* Registra un movimiento en estado REGISTRADO. El saldo de la cuenta cambia al aprobarlo.
   Para los que disminuyen el saldo valida que no supere el saldo disponible. */
CREATE OR ALTER PROCEDURE dbo.sp_movimiento_registrar
    @id_cuenta_pagar    INT,
    @id_tipo_movimiento INT,
    @monto              DECIMAL(18,2),
    @fecha_movimiento   DATE = NULL,
    @id_tipo_pago       INT = NULL,
    @referencia         VARCHAR(50) = NULL,
    @descripcion        VARCHAR(150) = NULL,
    @id_programacion    INT = NULL,
    @codigo_erp         VARCHAR(50) = NULL,
    @id_usuario         INT,
    @id_movimiento      INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    SET @fecha_movimiento = ISNULL(@fecha_movimiento, CAST(SYSDATETIME() AS DATE));

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_mov_registrar;

        DECLARE @estado_cuenta VARCHAR(20), @efecto VARCHAR(20);

        -- Bloquea la cuenta para que dos registros simultáneos no comprometan el mismo saldo
        SELECT @estado_cuenta = e.codigo
        FROM dbo.CP04_cuentas_por_pagar c WITH (UPDLOCK, HOLDLOCK)
        JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
        WHERE c.id_cuenta_pagar = @id_cuenta_pagar;

        SELECT @efecto = e.codigo
        FROM dbo.CP02_tipos_movimiento t JOIN dbo.CP08_estados e ON e.id_estado = t.id_efecto
        WHERE t.id_tipo_movimiento = @id_tipo_movimiento AND t.estado = 1;

        IF @estado_cuenta IS NULL
            THROW 50040, 'La cuenta por pagar no existe.', 1;
        IF @estado_cuenta = 'ANULADA'
            THROW 50041, 'La cuenta por pagar está anulada.', 1;
        IF @efecto IS NULL
            THROW 50042, 'El tipo de movimiento no existe o está inactivo.', 1;
        IF @monto <= 0
            THROW 50043, 'El monto debe ser mayor que cero.', 1;
        IF @efecto = 'DISMINUYE' AND @monto > dbo.fn_saldo_disponible(@id_cuenta_pagar)
            THROW 50044, 'El monto supera el saldo disponible de la cuenta.', 1;
        IF @id_tipo_pago IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM dbo.CP03_tipos_pago WHERE id_tipo_pago = @id_tipo_pago AND estado = 1)
            THROW 50045, 'El tipo de pago no existe o está inactivo.', 1;

        INSERT INTO dbo.CP07_movimientos (id_cuenta_pagar, id_tipo_movimiento, numero_movimiento, id_tipo_pago,
                    id_programacion, fecha_movimiento, monto, referencia, descripcion, id_estado,
                    id_usuario_registro, codigo_erp)
        SELECT @id_cuenta_pagar, @id_tipo_movimiento, ISNULL(MAX(numero_movimiento), 0) + 1, @id_tipo_pago,
               @id_programacion, @fecha_movimiento, @monto, @referencia, @descripcion,
               dbo.fn_estado_id('MOVIMIENTO', 'REGISTRADO'), @id_usuario, @codigo_erp
        FROM dbo.CP07_movimientos
        WHERE id_cuenta_pagar = @id_cuenta_pagar;
        SET @id_movimiento = SCOPE_IDENTITY();

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_mov_registrar;
        THROW;
    END CATCH;
END;
GO

/* Registra un pago (R2). Si la fecha está dentro del plazo de pronto pago y el monto
   cancela el saldo menos el descuento, registra también el movimiento de descuento. */
CREATE OR ALTER PROCEDURE dbo.sp_pago_registrar
    @id_cuenta_pagar   INT,
    @monto             DECIMAL(18,2),
    @id_tipo_pago      INT,
    @fecha_pago        DATE = NULL,
    @referencia        VARCHAR(50) = NULL,
    @descripcion       VARCHAR(150) = NULL,
    @aplicar_descuento BIT = 1,
    @id_programacion   INT = NULL,
    @id_usuario        INT,
    @id_movimiento     INT OUTPUT,
    @monto_descuento   DECIMAL(18,2) = 0 OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_pago = ISNULL(@fecha_pago, CAST(SYSDATETIME() AS DATE));
    SET @monto_descuento = 0;

    IF @id_tipo_pago IS NULL
        THROW 50050, 'Un pago requiere tipo de pago.', 1;

    DECLARE @id_tipo_pago_mov INT = (SELECT id_tipo_movimiento FROM dbo.CP02_tipos_movimiento WHERE nombre = 'Pago'),
            @id_tipo_desc INT = (SELECT id_tipo_movimiento FROM dbo.CP02_tipos_movimiento WHERE nombre = 'Descuento por pronto pago'),
            @id_desc INT;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_pago_registrar;

        IF @aplicar_descuento = 1
        BEGIN
            DECLARE @descuento DECIMAL(18,2) = dbo.fn_descuento_pronto_pago(@id_cuenta_pagar, @fecha_pago);
            IF @descuento > 0 AND @monto + @descuento = dbo.fn_saldo_disponible(@id_cuenta_pagar)
                SET @monto_descuento = @descuento;
        END;

        EXEC dbo.sp_movimiento_registrar @id_cuenta_pagar = @id_cuenta_pagar, @id_tipo_movimiento = @id_tipo_pago_mov,
             @monto = @monto, @fecha_movimiento = @fecha_pago, @id_tipo_pago = @id_tipo_pago,
             @referencia = @referencia, @descripcion = @descripcion, @id_programacion = @id_programacion,
             @id_usuario = @id_usuario, @id_movimiento = @id_movimiento OUTPUT;

        IF @monto_descuento > 0
            EXEC dbo.sp_movimiento_registrar @id_cuenta_pagar = @id_cuenta_pagar, @id_tipo_movimiento = @id_tipo_desc,
                 @monto = @monto_descuento, @fecha_movimiento = @fecha_pago, @referencia = @referencia,
                 @descripcion = 'Descuento por pronto pago', @id_usuario = @id_usuario, @id_movimiento = @id_desc OUTPUT;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_pago_registrar;
        THROW;
    END CATCH;
END;
GO

/* Aprueba un movimiento: aplica su efecto al saldo, actualiza el estado de la cuenta y,
   si es un pago, deja en cola la notificación al proveedor (R9). */
CREATE OR ALTER PROCEDURE dbo.sp_movimiento_aprobar
    @id_movimiento INT,
    @id_usuario    INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF dbo.fn_usuario_puede_aprobar(@id_usuario) = 0
        THROW 50060, 'El rol del usuario no permite aprobar movimientos.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_mov_aprobar;

        DECLARE @id_cuenta INT, @monto DECIMAL(18,2), @estado VARCHAR(20), @efecto VARCHAR(20),
                @registra INT, @tipo VARCHAR(50), @saldo DECIMAL(18,2), @nuevo_saldo DECIMAL(18,2);

        SELECT @id_cuenta = m.id_cuenta_pagar, @monto = m.monto, @estado = em.codigo,
               @efecto = ef.codigo, @registra = m.id_usuario_registro, @tipo = t.nombre
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP08_estados em         ON em.id_estado = m.id_estado
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        WHERE m.id_movimiento = @id_movimiento;

        IF @id_cuenta IS NULL THROW 50061, 'El movimiento no existe.', 1;
        IF @estado <> 'REGISTRADO' THROW 50062, 'Solo se pueden aprobar movimientos en estado REGISTRADO.', 1;
        IF @registra = @id_usuario THROW 50063, 'Quien registró el movimiento no puede aprobarlo.', 1;

        SELECT @saldo = saldo_pendiente FROM dbo.CP04_cuentas_por_pagar WITH (UPDLOCK, HOLDLOCK)
        WHERE id_cuenta_pagar = @id_cuenta;

        SET @nuevo_saldo = IIF(@efecto = 'DISMINUYE', @saldo - @monto, @saldo + @monto);
        IF @nuevo_saldo < 0 THROW 50064, 'El movimiento deja el saldo de la cuenta en negativo.', 1;

        UPDATE dbo.CP07_movimientos
        SET id_estado = dbo.fn_estado_id('MOVIMIENTO', 'APROBADO'),
            id_usuario_aprueba = @id_usuario, fecha_aprobacion = SYSDATETIME()
        WHERE id_movimiento = @id_movimiento;

        UPDATE dbo.CP04_cuentas_por_pagar
        SET saldo_pendiente = @nuevo_saldo,
            id_estado = dbo.fn_estado_id('CUENTA_PAGAR',
                        dbo.fn_estado_cuenta_codigo(@nuevo_saldo, monto_total, fecha_vencimiento, CAST(SYSDATETIME() AS DATE)))
        WHERE id_cuenta_pagar = @id_cuenta;

        IF @tipo = 'Pago'
            INSERT INTO dbo.CP18_notificaciones (id_movimiento, id_proveedor, correo_destino, asunto, id_estado)
            SELECT @id_movimiento, p.id_proveedor, p.correo,
                   CONCAT('Comprobante de pago - factura ', c.numero_factura),
                   dbo.fn_estado_id('NOTIFICACION', 'PENDIENTE')
            FROM dbo.CP04_cuentas_por_pagar c
            JOIN dbo.CP01_proveedores p ON p.id_proveedor = c.id_proveedor
            WHERE c.id_cuenta_pagar = @id_cuenta;

        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_movimiento, 'APROBAR';
        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_mov_aprobar;
        THROW;
    END CATCH;
END;
GO

-- Anula un movimiento. Si estaba aprobado revierte su efecto sobre el saldo.
CREATE OR ALTER PROCEDURE dbo.sp_movimiento_anular
    @id_movimiento INT,
    @motivo        VARCHAR(150),
    @id_usuario    INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF dbo.fn_usuario_puede_aprobar(@id_usuario) = 0
        THROW 50070, 'El rol del usuario no permite anular movimientos.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_mov_anular;

        DECLARE @id_cuenta INT, @monto DECIMAL(18,2), @estado VARCHAR(20), @efecto VARCHAR(20),
                @conciliado BIT, @estado_lote VARCHAR(20), @nuevo_saldo DECIMAL(18,2);

        SELECT @id_cuenta = m.id_cuenta_pagar, @monto = m.monto, @estado = em.codigo,
               @efecto = ef.codigo, @conciliado = m.conciliado, @estado_lote = el.codigo
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP08_estados em         ON em.id_estado = m.id_estado
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        LEFT JOIN dbo.CP16_lotes_transferencia l ON l.id_lote = m.id_lote
        LEFT JOIN dbo.CP08_estados el            ON el.id_estado = l.id_estado
        WHERE m.id_movimiento = @id_movimiento;

        IF @id_cuenta IS NULL THROW 50071, 'El movimiento no existe.', 1;
        IF @estado = 'ANULADO' THROW 50072, 'El movimiento ya está anulado.', 1;
        IF @conciliado = 1 THROW 50073, 'No se puede anular un movimiento conciliado con el banco.', 1;
        IF @estado_lote IN ('ENVIADO', 'PROCESADO')
            THROW 50074, 'El movimiento pertenece a un lote ya enviado al banco.', 1;

        IF @estado = 'APROBADO'
        BEGIN
            SELECT @nuevo_saldo = IIF(@efecto = 'DISMINUYE', saldo_pendiente + @monto, saldo_pendiente - @monto)
            FROM dbo.CP04_cuentas_por_pagar WITH (UPDLOCK, HOLDLOCK) WHERE id_cuenta_pagar = @id_cuenta;
            IF @nuevo_saldo < 0 THROW 50075, 'Anular el movimiento deja el saldo en negativo.', 1;

            UPDATE dbo.CP04_cuentas_por_pagar
            SET saldo_pendiente = @nuevo_saldo,
                id_estado = dbo.fn_estado_id('CUENTA_PAGAR',
                            dbo.fn_estado_cuenta_codigo(@nuevo_saldo, monto_total, fecha_vencimiento, CAST(SYSDATETIME() AS DATE)))
            WHERE id_cuenta_pagar = @id_cuenta;
        END;

        UPDATE dbo.CP07_movimientos
        SET id_estado = dbo.fn_estado_id('MOVIMIENTO', 'ANULADO'), id_lote = NULL
        WHERE id_movimiento = @id_movimiento;

        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_movimiento, 'ANULAR', NULL, @motivo;
        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_mov_anular;
        THROW;
    END CATCH;
END;
GO

/* ---------- 7.5 Lotes de transferencia y conciliación bancaria ---------- */

-- Agrupa en un lote los pagos aprobados de un banco que aún no están en ningún lote (R9)
CREATE OR ALTER PROCEDURE dbo.sp_lote_generar
    @id_banco   INT,
    @id_usuario INT,
    @id_lote    INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF dbo.fn_usuario_puede_aprobar(@id_usuario) = 0
        THROW 50080, 'El rol del usuario no permite generar lotes.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_lote_generar;

        DECLARE @pagos TABLE (id_movimiento INT PRIMARY KEY, monto DECIMAL(18,2));
        INSERT INTO @pagos
        SELECT m.id_movimiento, m.monto
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP03_tipos_pago tp       ON tp.id_tipo_pago = m.id_tipo_pago
        JOIN dbo.CP02_tipos_movimiento t  ON t.id_tipo_movimiento = m.id_tipo_movimiento
        WHERE tp.id_banco = @id_banco AND t.nombre = 'Pago' AND m.id_lote IS NULL
          AND m.id_estado = dbo.fn_estado_id('MOVIMIENTO', 'APROBADO');

        IF NOT EXISTS (SELECT 1 FROM @pagos)
            THROW 50081, 'No hay pagos aprobados pendientes de enviar para este banco.', 1;

        INSERT INTO dbo.CP16_lotes_transferencia (id_banco, cantidad_pagos, monto_total, id_estado)
        SELECT @id_banco, COUNT(*), SUM(monto), dbo.fn_estado_id('LOTE', 'GENERADO') FROM @pagos;
        SET @id_lote = SCOPE_IDENTITY();

        UPDATE m SET id_lote = @id_lote
        FROM dbo.CP07_movimientos m JOIN @pagos p ON p.id_movimiento = m.id_movimiento;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_lote_generar;
        THROW;
    END CATCH;
END;
GO

-- Cambia el estado del lote: GENERADO -> ENVIADO -> PROCESADO, o ENVIADO -> RECHAZADO.
-- Un lote rechazado libera sus pagos para incluirlos en otro lote.
CREATE OR ALTER PROCEDURE dbo.sp_lote_cambiar_estado
    @id_lote       INT,
    @codigo_estado VARCHAR(20),
    @id_usuario    INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    DECLARE @actual VARCHAR(20) = (SELECT e.codigo FROM dbo.CP16_lotes_transferencia l
                                   JOIN dbo.CP08_estados e ON e.id_estado = l.id_estado WHERE l.id_lote = @id_lote);
    IF @actual IS NULL THROW 50082, 'El lote no existe.', 1;
    IF NOT ((@actual = 'GENERADO' AND @codigo_estado = 'ENVIADO')
         OR (@actual = 'ENVIADO'  AND @codigo_estado IN ('PROCESADO', 'RECHAZADO')))
        THROW 50083, 'Cambio de estado de lote no permitido.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_lote_estado;

        UPDATE dbo.CP16_lotes_transferencia
        SET id_estado = dbo.fn_estado_id('LOTE', @codigo_estado),
            fecha_envio = IIF(@codigo_estado = 'ENVIADO', SYSDATETIME(), fecha_envio)
        WHERE id_lote = @id_lote;

        IF @codigo_estado = 'RECHAZADO'
            UPDATE dbo.CP07_movimientos SET id_lote = NULL WHERE id_lote = @id_lote;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_lote_estado;
        THROW;
    END CATCH;
END;
GO

/* Conciliación automática con el extracto bancario (R2, R9).
   @extracto es un arreglo JSON: [{"fecha":"2026-10-15","referencia":"TRX-001","monto":1500.00}]
   Une cada línea con un pago aprobado del mismo banco por referencia y monto, con una
   diferencia de fechas de hasta @tolerancia_dias. Devuelve cada línea con su resultado. */
CREATE OR ALTER PROCEDURE dbo.sp_conciliar_extracto
    @id_banco        INT,
    @extracto        NVARCHAR(MAX),
    @tolerancia_dias INT = 3,
    @id_usuario      INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF ISJSON(@extracto) = 0
        THROW 50085, 'El extracto debe ser un arreglo JSON.', 1;

    DECLARE @lineas TABLE (linea INT PRIMARY KEY, fecha DATE, referencia VARCHAR(50), monto DECIMAL(18,2),
                           id_movimiento INT NULL);
    INSERT INTO @lineas (linea, fecha, referencia, monto)
    SELECT CAST(j.[key] AS INT) + 1, v.fecha, v.referencia, v.monto
    FROM OPENJSON(@extracto) j
    CROSS APPLY OPENJSON(j.[value]) WITH (fecha DATE, referencia VARCHAR(50), monto DECIMAL(18,2)) v;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_conciliar;

        -- Cada movimiento se concilia con una sola línea y cada línea con un solo movimiento
        WITH candidatos AS (
            SELECT l.linea, m.id_movimiento,
                   ROW_NUMBER() OVER (PARTITION BY m.id_movimiento
                                      ORDER BY ABS(DATEDIFF(DAY, m.fecha_movimiento, l.fecha)), l.linea) AS rn_mov,
                   ROW_NUMBER() OVER (PARTITION BY l.linea
                                      ORDER BY ABS(DATEDIFF(DAY, m.fecha_movimiento, l.fecha)), m.id_movimiento) AS rn_lin
            FROM @lineas l
            JOIN dbo.CP07_movimientos m ON m.referencia = l.referencia AND m.monto = l.monto AND m.conciliado = 0
            JOIN dbo.CP03_tipos_pago tp ON tp.id_tipo_pago = m.id_tipo_pago AND tp.id_banco = @id_banco
            WHERE m.id_estado = dbo.fn_estado_id('MOVIMIENTO', 'APROBADO')
              AND ABS(DATEDIFF(DAY, m.fecha_movimiento, l.fecha)) <= @tolerancia_dias
        )
        UPDATE l SET id_movimiento = c.id_movimiento
        FROM @lineas l JOIN candidatos c ON c.linea = l.linea AND c.rn_mov = 1 AND c.rn_lin = 1;

        UPDATE m SET conciliado = 1, fecha_conciliacion = SYSDATETIME()
        FROM dbo.CP07_movimientos m JOIN @lineas l ON l.id_movimiento = m.id_movimiento;

        DECLARE @conciliados INT = @@ROWCOUNT;
        IF @conciliados > 0
        BEGIN
            DECLARE @detalle NVARCHAR(MAX) = (SELECT linea, referencia, monto, id_movimiento FROM @lineas
                                              WHERE id_movimiento IS NOT NULL FOR JSON PATH);
            EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_banco, 'CONCILIAR', NULL, @detalle;
        END;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_conciliar;
        THROW;
    END CATCH;

    SELECT linea, fecha, referencia, monto, id_movimiento,
           IIF(id_movimiento IS NULL, 'SIN CONCILIAR', 'CONCILIADO') AS resultado
    FROM @lineas ORDER BY linea;
END;
GO

/* ---------- 7.6 Programación de pagos y notificaciones ---------- */

CREATE OR ALTER PROCEDURE dbo.sp_programacion_registrar
    @id_proveedor       INT,
    @descripcion        VARCHAR(150),
    @id_frecuencia      INT,
    @dia_ejecucion      TINYINT,
    @monto              DECIMAL(18,2),
    @fecha_inicio       DATE,
    @fecha_fin          DATE = NULL,
    @enviar_comprobante BIT = 1,
    @id_usuario         INT,
    @id_programacion    INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    DECLARE @meses TINYINT = (SELECT meses_intervalo FROM dbo.CP13_frecuencias
                              WHERE id_frecuencia = @id_frecuencia AND estado = 1);
    IF @meses IS NULL THROW 50100, 'La frecuencia no existe o está inactiva.', 1;

    -- Frecuencias mensuales o mayores: primer @dia_ejecucion a partir de la fecha de inicio.
    -- Frecuencias en días: empieza en la fecha de inicio.
    DECLARE @proxima DATE = @fecha_inicio;
    IF @meses > 0
    BEGIN
        SET @proxima = dbo.fn_ajustar_dia(@fecha_inicio, @dia_ejecucion);
        IF @proxima < @fecha_inicio
            SET @proxima = dbo.fn_ajustar_dia(DATEADD(MONTH, 1, @fecha_inicio), @dia_ejecucion);
    END;

    INSERT INTO dbo.CP10_programacion_pagos (id_proveedor, descripcion, id_frecuencia, dia_ejecucion, monto,
                fecha_inicio, fecha_fin, proxima_ejecucion, enviar_comprobante)
    VALUES (@id_proveedor, @descripcion, @id_frecuencia, @dia_ejecucion, @monto, @fecha_inicio, @fecha_fin,
            @proxima, @enviar_comprobante);
    SET @id_programacion = SCOPE_IDENTITY();
END;
GO

/* Ejecuta las programaciones vencidas a @fecha_corte (tarea diaria del Agente SQL).
   El monto se aplica a las cuentas abiertas del proveedor, de la más antigua a la más
   reciente. Cada programación se procesa en su propia transacción. */
CREATE OR ALTER PROCEDURE dbo.sp_programaciones_ejecutar
    @fecha_corte  DATE = NULL,
    @id_tipo_pago INT,
    @id_usuario   INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    DECLARE @resultado TABLE (id_programacion INT, fecha_ejecucion DATE, monto_aplicado DECIMAL(18,2),
                              pagos_registrados INT, resultado VARCHAR(250));
    DECLARE @id_prog INT, @id_prov INT, @monto DECIMAL(18,2), @fecha DATE, @id_frec INT, @dia TINYINT,
            @fin DATE, @restante DECIMAL(18,2), @id_cuenta INT, @aplicar DECIMAL(18,2), @id_mov INT, @pagos INT,
            @desc VARCHAR(150), @fecha_ejecutada DATE;

    DECLARE prog CURSOR LOCAL FAST_FORWARD FOR
        SELECT id_programacion FROM dbo.CP10_programacion_pagos
        WHERE estado = 1 AND proxima_ejecucion <= @fecha_corte ORDER BY proxima_ejecucion;
    OPEN prog;
    FETCH NEXT FROM prog INTO @id_prog;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @id_prov = id_proveedor, @monto = monto, @fecha = proxima_ejecucion, @id_frec = id_frecuencia,
               @dia = dia_ejecucion, @fin = fecha_fin, @desc = descripcion
        FROM dbo.CP10_programacion_pagos WHERE id_programacion = @id_prog;

        -- Si hay varias ejecuciones atrasadas se procesan todas
        WHILE @fecha <= @fecha_corte AND (@fin IS NULL OR @fecha <= @fin)
        BEGIN
            BEGIN TRY
                BEGIN TRANSACTION;
                SELECT @restante = @monto, @pagos = 0;

                WHILE @restante > 0
                BEGIN
                    SET @id_cuenta = NULL;
                    SELECT TOP (1) @id_cuenta = c.id_cuenta_pagar,
                           @aplicar = IIF(dbo.fn_saldo_disponible(c.id_cuenta_pagar) < @restante,
                                          dbo.fn_saldo_disponible(c.id_cuenta_pagar), @restante)
                    FROM dbo.CP04_cuentas_por_pagar c
                    JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
                    WHERE c.id_proveedor = @id_prov AND e.codigo IN ('PENDIENTE', 'PARCIAL', 'VENCIDA')
                      AND dbo.fn_saldo_disponible(c.id_cuenta_pagar) > 0
                    ORDER BY c.fecha_vencimiento, c.id_cuenta_pagar;

                    IF @id_cuenta IS NULL BREAK;

                    EXEC dbo.sp_pago_registrar @id_cuenta_pagar = @id_cuenta, @monto = @aplicar,
                         @id_tipo_pago = @id_tipo_pago, @fecha_pago = @fecha, @descripcion = @desc,
                         @aplicar_descuento = 0, @id_programacion = @id_prog, @id_usuario = @id_usuario,
                         @id_movimiento = @id_mov OUTPUT;
                    SELECT @restante -= @aplicar, @pagos += 1;
                END;

                IF @pagos = 0
                BEGIN
                    -- Sin cuentas que pagar: la programación queda pendiente para la próxima corrida
                    ROLLBACK TRANSACTION;
                    INSERT INTO @resultado VALUES (@id_prog, @fecha, 0, 0, 'El proveedor no tiene cuentas con saldo');
                    BREAK;
                END;

                SELECT @fecha_ejecutada = @fecha, @fecha = dbo.fn_siguiente_ejecucion(@fecha, @id_frec, @dia);
                UPDATE dbo.CP10_programacion_pagos
                SET proxima_ejecucion = @fecha,
                    estado = IIF(@fin IS NOT NULL AND @fecha > @fin, 0, 1)
                WHERE id_programacion = @id_prog;

                COMMIT TRANSACTION;
                INSERT INTO @resultado VALUES (@id_prog, @fecha_ejecutada, @monto - @restante, @pagos,
                                               IIF(@restante > 0, 'Aplicado parcialmente: saldo insuficiente', 'OK'));
            END TRY
            BEGIN CATCH
                IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
                INSERT INTO @resultado VALUES (@id_prog, @fecha, 0, 0, LEFT(ERROR_MESSAGE(), 250));
                BREAK;
            END CATCH;
        END;

        FETCH NEXT FROM prog INTO @id_prog;
    END;
    CLOSE prog;
    DEALLOCATE prog;

    IF EXISTS (SELECT 1 FROM @resultado)
    BEGIN
        DECLARE @json NVARCHAR(MAX) = (SELECT * FROM @resultado FOR JSON PATH);
        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP10_programacion_pagos', 'lote', 'EJECUTAR', NULL, @json;
    END;
    SELECT * FROM @resultado;
END;
GO

-- Cola de notificaciones pendientes para el servicio que envía los correos
CREATE OR ALTER PROCEDURE dbo.sp_notificaciones_pendientes
    @maximo INT = 50
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP (@maximo) n.id_notificacion, n.id_movimiento, n.id_proveedor, p.razon_social,
           n.correo_destino, n.asunto, m.monto, m.fecha_movimiento, m.referencia
    FROM dbo.CP18_notificaciones n
    JOIN dbo.CP01_proveedores p     ON p.id_proveedor = n.id_proveedor
    LEFT JOIN dbo.CP07_movimientos m ON m.id_movimiento = n.id_movimiento
    WHERE n.id_estado = dbo.fn_estado_id('NOTIFICACION', 'PENDIENTE')
    ORDER BY n.fecha_envio;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_notificacion_marcar
    @id_notificacion INT,
    @codigo_estado   VARCHAR(20)      -- ENVIADA o FALLIDA
AS
BEGIN
    SET NOCOUNT ON;
    IF @codigo_estado NOT IN ('ENVIADA', 'FALLIDA')
        THROW 50110, 'Estado de notificación no válido.', 1;
    UPDATE dbo.CP18_notificaciones
    SET id_estado = dbo.fn_estado_id('NOTIFICACION', @codigo_estado), fecha_envio = SYSDATETIME()
    WHERE id_notificacion = @id_notificacion;
END;
GO

-- Resultado de enviar un registro de la cola al ERP
CREATE OR ALTER PROCEDURE dbo.sp_sincronizacion_marcar
    @id_sincronizacion INT,
    @codigo_estado     VARCHAR(20),   -- EXITOSA o ERROR
    @mensaje_error     VARCHAR(250) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF @codigo_estado NOT IN ('EXITOSA', 'ERROR')
        THROW 50111, 'Estado de sincronización no válido.', 1;
    UPDATE dbo.CP14_sincronizacion_erp
    SET id_estado = dbo.fn_estado_id('SINCRONIZACION', @codigo_estado),
        mensaje_error = IIF(@codigo_estado = 'ERROR', @mensaje_error, NULL),
        fecha_sincronizacion = SYSDATETIME()
    WHERE id_sincronizacion = @id_sincronizacion;
END;
GO

/* ---------- 7.7 Reportes financieros (R3) ---------- */

-- Cuentas vencidas y por vencer en los próximos @dias_horizonte días
CREATE OR ALTER PROCEDURE dbo.sp_reporte_vencimientos
    @fecha_corte    DATE = NULL,
    @dias_horizonte INT = 30
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    SELECT p.razon_social, c.numero_factura, c.fecha_emision, c.fecha_vencimiento, c.monto_total,
           c.saldo_pendiente, e.nombre AS estado,
           dbo.fn_dias_vencido(c.fecha_vencimiento, @fecha_corte) AS dias_vencido,
           DATEDIFF(DAY, @fecha_corte, c.fecha_vencimiento) AS dias_para_vencer,
           c.fecha_limite_descuento,
           dbo.fn_descuento_pronto_pago(c.id_cuenta_pagar, @fecha_corte) AS descuento_disponible_hoy
    FROM dbo.CP04_cuentas_por_pagar c
    JOIN dbo.CP01_proveedores p ON p.id_proveedor = c.id_proveedor
    JOIN dbo.CP08_estados e     ON e.id_estado = c.id_estado
    WHERE c.saldo_pendiente > 0 AND e.codigo <> 'ANULADA'
      AND c.fecha_vencimiento <= DATEADD(DAY, @dias_horizonte, @fecha_corte)
    ORDER BY c.fecha_vencimiento, p.razon_social;
END;
GO

-- Antigüedad de saldos por proveedor (montos por rango de días vencidos)
CREATE OR ALTER PROCEDURE dbo.sp_reporte_antiguedad_saldos
    @fecha_corte DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    SELECT razon_social,
           SUM(IIF(rango = 'Por vencer',     saldo_pendiente, 0)) AS por_vencer,
           SUM(IIF(rango = '1-30 días',      saldo_pendiente, 0)) AS [1_30_dias],
           SUM(IIF(rango = '31-60 días',     saldo_pendiente, 0)) AS [31_60_dias],
           SUM(IIF(rango = '61-90 días',     saldo_pendiente, 0)) AS [61_90_dias],
           SUM(IIF(rango = 'Más de 90 días', saldo_pendiente, 0)) AS mas_90_dias,
           SUM(saldo_pendiente) AS total
    FROM dbo.fn_antiguedad_saldos(@fecha_corte)
    GROUP BY ROLLUP (razon_social)
    ORDER BY GROUPING(razon_social), total DESC;
END;
GO

-- Flujo de caja proyectado por semana o por mes, con saldo acumulado
CREATE OR ALTER PROCEDURE dbo.sp_reporte_flujo_caja
    @desde      DATE,
    @hasta      DATE,
    @agrupacion VARCHAR(10) = 'SEMANA'    -- SEMANA o MES
AS
BEGIN
    SET NOCOUNT ON;
    IF @agrupacion NOT IN ('SEMANA', 'MES')
        THROW 50120, 'La agrupación debe ser SEMANA o MES.', 1;

    WITH flujo AS (
        SELECT CASE WHEN @agrupacion = 'MES' THEN DATEFROMPARTS(YEAR(fecha), MONTH(fecha), 1)
                    -- lunes de la semana (1900-01-01 fue lunes)
                    ELSE DATEADD(DAY, -(DATEDIFF(DAY, '19000101', fecha) % 7), fecha) END AS periodo,
               origen, monto
        FROM dbo.fn_flujo_caja_proyectado(@desde, @hasta)
    )
    SELECT periodo,
           SUM(IIF(origen = 'Cuenta vencida',   monto, 0)) AS cuentas_vencidas,
           SUM(IIF(origen = 'Cuenta por pagar', monto, 0)) AS cuentas_por_pagar,
           SUM(IIF(origen = 'Pago programado',  monto, 0)) AS pagos_programados,
           SUM(monto) AS total_periodo,
           SUM(SUM(monto)) OVER (ORDER BY periodo ROWS UNBOUNDED PRECEDING) AS total_acumulado
    FROM flujo
    GROUP BY periodo
    ORDER BY periodo;
END;
GO

-- Historial y análisis de la relación con un proveedor (R7): resumen + movimientos
CREATE OR ALTER PROCEDURE dbo.sp_reporte_historial_proveedor
    @id_proveedor INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT p.razon_social, p.identificacion_tributaria, p.dias_credito,
           COUNT(c.id_cuenta_pagar)                       AS cuentas_registradas,
           ISNULL(SUM(c.monto_total), 0)                  AS monto_facturado,
           ISNULL(SUM(c.saldo_pendiente), 0)              AS saldo_pendiente,
           (SELECT ISNULL(SUM(monto), 0) FROM dbo.fn_historial_pagos_proveedor(@id_proveedor)
            WHERE tipo_movimiento = 'Pago')               AS total_pagado,
           (SELECT ISNULL(SUM(monto), 0) FROM dbo.fn_historial_pagos_proveedor(@id_proveedor)
            WHERE tipo_movimiento = 'Descuento por pronto pago') AS descuentos_obtenidos,
           (SELECT CAST(AVG(CAST(calificacion AS DECIMAL(4,2))) AS DECIMAL(4,2))
            FROM dbo.CP19_evaluaciones_proveedor WHERE id_proveedor = @id_proveedor) AS calificacion_promedio
    FROM dbo.CP01_proveedores p
    LEFT JOIN dbo.CP04_cuentas_por_pagar c ON c.id_proveedor = p.id_proveedor
         AND c.id_estado <> dbo.fn_estado_id('CUENTA_PAGAR', 'ANULADA')
    WHERE p.id_proveedor = @id_proveedor
    GROUP BY p.razon_social, p.identificacion_tributaria, p.dias_credito;

    SELECT * FROM dbo.fn_historial_pagos_proveedor(@id_proveedor)
    ORDER BY fecha_movimiento DESC, id_movimiento DESC;
END;
GO

-- Bitácora de auditoría filtrada para revisiones de cumplimiento (R5)
CREATE OR ALTER PROCEDURE dbo.sp_reporte_auditoria
    @desde      DATETIME2,
    @hasta      DATETIME2,
    @tabla      VARCHAR(60) = NULL,
    @id_usuario INT = NULL,
    @accion     VARCHAR(20) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT a.id_auditoria, a.fecha_hora, u.nombre_usuario, a.tabla_afectada, a.id_registro,
           a.accion, a.valor_anterior, a.valor_nuevo, a.direccion_ip
    FROM dbo.CP09_auditoria_acciones a
    LEFT JOIN dbo.CP12_usuarios u ON u.id_usuario = a.id_usuario
    WHERE a.fecha_hora >= @desde AND a.fecha_hora < @hasta
      AND (@tabla IS NULL OR a.tabla_afectada = @tabla)
      AND (@id_usuario IS NULL OR a.id_usuario = @id_usuario)
      AND (@accion IS NULL OR a.accion = @accion)
    ORDER BY a.fecha_hora DESC, a.id_auditoria DESC;
END;
GO

/* ---------- 7.8 Mantenimiento (R6) ---------- */

-- Compara el saldo guardado de cada cuenta con el que resulta de sus movimientos aprobados
CREATE OR ALTER PROCEDURE dbo.sp_verificar_consistencia_saldos
AS
BEGIN
    SET NOCOUNT ON;
    SELECT c.id_cuenta_pagar, c.numero_factura, c.monto_total, c.saldo_pendiente,
           c.monto_total - SUM(x.efecto) AS saldo_calculado
    FROM dbo.CP04_cuentas_por_pagar c
    LEFT JOIN dbo.CP07_movimientos m ON m.id_cuenta_pagar = c.id_cuenta_pagar
         AND m.id_estado = dbo.fn_estado_id('MOVIMIENTO', 'APROBADO')
    LEFT JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
    LEFT JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
    CROSS APPLY (SELECT CASE ef.codigo WHEN 'DISMINUYE' THEN m.monto WHEN 'AUMENTA' THEN -m.monto ELSE 0 END AS efecto) x
    WHERE c.id_estado <> dbo.fn_estado_id('CUENTA_PAGAR', 'ANULADA')
    GROUP BY c.id_cuenta_pagar, c.numero_factura, c.monto_total, c.saldo_pendiente
    HAVING c.saldo_pendiente <> c.monto_total - SUM(x.efecto);
END;
GO

-- Reorganiza o reconstruye los índices según su fragmentación
CREATE OR ALTER PROCEDURE dbo.sp_mantenimiento_indices
    @umbral_reorganizar FLOAT = 10,
    @umbral_reconstruir FLOAT = 30,
    @min_paginas        INT = 100
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @sql NVARCHAR(500);
    DECLARE @acciones TABLE (tabla SYSNAME, indice SYSNAME, fragmentacion FLOAT, accion VARCHAR(12));

    INSERT INTO @acciones
    SELECT OBJECT_NAME(s.object_id), i.name, s.avg_fragmentation_in_percent,
           IIF(s.avg_fragmentation_in_percent >= @umbral_reconstruir, 'REBUILD', 'REORGANIZE')
    FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') s
    JOIN sys.indexes i ON i.object_id = s.object_id AND i.index_id = s.index_id
    WHERE s.index_id > 0 AND s.page_count >= @min_paginas
      AND s.avg_fragmentation_in_percent >= @umbral_reorganizar;

    DECLARE c CURSOR LOCAL FAST_FORWARD FOR
        SELECT CONCAT('ALTER INDEX ', QUOTENAME(indice), ' ON dbo.', QUOTENAME(tabla), ' ', accion) FROM @acciones;
    OPEN c;
    FETCH NEXT FROM c INTO @sql;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        EXEC sys.sp_executesql @sql;
        FETCH NEXT FROM c INTO @sql;
    END;
    CLOSE c;
    DEALLOCATE c;

    EXEC sys.sp_updatestats;
    SELECT * FROM @acciones;
END;
GO

-- Respaldo completo con verificación. @ruta: carpeta del servidor, por ejemplo 'C:\Respaldos\'
CREATE OR ALTER PROCEDURE dbo.sp_respaldo_base
    @ruta       NVARCHAR(200),
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    DECLARE @base SYSNAME = DB_NAME();
    DECLARE @archivo NVARCHAR(260) = CONCAT(@ruta, IIF(RIGHT(@ruta, 1) = '\', '', '\'),
            @base, '_', FORMAT(SYSDATETIME(), 'yyyyMMdd_HHmmss'), '.bak');

    BACKUP DATABASE @base TO DISK = @archivo WITH INIT, CHECKSUM, STATS = 25;
    RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM;

    EXEC dbo.sp_auditoria_registrar @id_usuario, 'BASE_DE_DATOS', 'respaldo', 'RESPALDO', NULL, @archivo;
    SELECT @archivo AS archivo_respaldo;
END;
GO

/* =====================================================================================
   8. ROLES DE BASE DE DATOS Y PERMISOS (R10)
   Las aplicaciones trabajan solo con procedimientos: ningún rol escribe directo en las tablas.
   Para usarlos: ALTER ROLE rol_cxp_tesoreria ADD MEMBER <usuario_de_base_de_datos>;
   ===================================================================================== */
IF DATABASE_PRINCIPAL_ID('rol_cxp_operacion') IS NULL CREATE ROLE rol_cxp_operacion;
IF DATABASE_PRINCIPAL_ID('rol_cxp_tesoreria') IS NULL CREATE ROLE rol_cxp_tesoreria;
IF DATABASE_PRINCIPAL_ID('rol_cxp_auditoria') IS NULL CREATE ROLE rol_cxp_auditoria;
GO

-- Operación (contabilidad): registra proveedores, cuentas y movimientos
GRANT EXECUTE ON dbo.sp_usuario_autenticar          TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_usuario_cambiar_contrasena  TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_proveedor_guardar           TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_proveedor_evaluar           TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_cuenta_pagar_registrar      TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_movimiento_registrar        TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_pago_registrar              TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_programacion_registrar      TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_reporte_vencimientos        TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_reporte_antiguedad_saldos   TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_reporte_flujo_caja          TO rol_cxp_operacion;
GRANT EXECUTE ON dbo.sp_reporte_historial_proveedor TO rol_cxp_operacion;

-- Tesorería: todo lo de operación más aprobar, anular, lotes, conciliación y programaciones
ALTER ROLE rol_cxp_operacion ADD MEMBER rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_movimiento_aprobar          TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_movimiento_anular           TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_cuenta_pagar_anular         TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_lote_generar                TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_lote_cambiar_estado         TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_conciliar_extracto          TO rol_cxp_tesoreria;
GRANT EXECUTE ON dbo.sp_programaciones_ejecutar     TO rol_cxp_tesoreria;

-- Auditoría: solo lectura de reportes y bitácora
GRANT EXECUTE ON dbo.sp_reporte_auditoria             TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_reporte_vencimientos          TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_reporte_antiguedad_saldos     TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_reporte_historial_proveedor   TO rol_cxp_auditoria;
GRANT EXECUTE ON dbo.sp_verificar_consistencia_saldos TO rol_cxp_auditoria;
GRANT SELECT  ON dbo.CP09_auditoria_acciones          TO rol_cxp_auditoria;

-- Nadie modifica la bitácora ni las tablas directamente
DENY INSERT, UPDATE, DELETE ON dbo.CP09_auditoria_acciones TO rol_cxp_operacion, rol_cxp_auditoria;
DENY INSERT, UPDATE, DELETE ON SCHEMA::dbo TO rol_cxp_auditoria;
GO

PRINT 'Base de datos CuentasPorPagarDB creada correctamente.';
GO
