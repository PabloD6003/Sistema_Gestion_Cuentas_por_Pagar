/* =====================================================================================
   Data Warehouse de Cuentas por Pagar.
   Base separada, misma instancia. Moneda única CRC. ETL batch diario.
   ===================================================================================== */
USE master;
GO
IF DB_ID(N'CuentasPorPagarDW') IS NULL
    CREATE DATABASE CuentasPorPagarDW;
GO
USE CuentasPorPagarDW;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF SCHEMA_ID(N'dim') IS NULL EXEC(N'CREATE SCHEMA dim');
IF SCHEMA_ID(N'fact') IS NULL EXEC(N'CREATE SCHEMA fact');
IF SCHEMA_ID(N'etl') IS NULL EXEC(N'CREATE SCHEMA etl');
IF SCHEMA_ID(N'rpt') IS NULL EXEC(N'CREATE SCHEMA rpt');
GO

/* -------------------------------------------------------------------------------------
   DIMENSIONES
   ------------------------------------------------------------------------------------- */
IF OBJECT_ID(N'dim.tiempo', N'U') IS NULL
CREATE TABLE dim.tiempo (
    tiempo_key      INT          NOT NULL CONSTRAINT PK_dim_tiempo PRIMARY KEY,
    fecha           DATE         NOT NULL CONSTRAINT UQ_dim_tiempo_fecha UNIQUE,
    anio            SMALLINT     NOT NULL,
    trimestre       TINYINT      NOT NULL,
    mes             TINYINT      NOT NULL,
    nombre_mes      VARCHAR(20)  NOT NULL,
    anio_mes        CHAR(7)      NOT NULL,
    semana          TINYINT      NOT NULL,
    dia             TINYINT      NOT NULL,
    dia_semana      TINYINT      NOT NULL,
    nombre_dia      VARCHAR(15)  NOT NULL,
    es_fin_semana   BIT          NOT NULL,
    es_feriado      BIT          NOT NULL CONSTRAINT DF_dim_tiempo_feriado DEFAULT (0)
);
GO

IF OBJECT_ID(N'dim.proveedor', N'U') IS NULL
CREATE TABLE dim.proveedor (
    proveedor_key             INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dim_proveedor PRIMARY KEY,
    id_proveedor              INT           NOT NULL,
    identificacion_tributaria VARCHAR(30)   NOT NULL,
    razon_social              VARCHAR(150)  NOT NULL,
    pais                      VARCHAR(60)   NULL,
    dias_credito              INT           NULL,
    banco                     VARCHAR(80)   NULL,
    cuenta_bancaria           VARCHAR(34)   NULL,
    estado                    BIT           NOT NULL,
    fecha_inicio_validez      DATETIME2     NOT NULL,
    fecha_fin_validez         DATETIME2     NULL,
    es_actual                 BIT           NOT NULL,
    version                   INT           NOT NULL,
    hash_atributos            CHAR(64)      NOT NULL
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_dim_proveedor_actual'
               AND object_id = OBJECT_ID(N'dim.proveedor'))
    CREATE UNIQUE INDEX UQ_dim_proveedor_actual ON dim.proveedor (id_proveedor)
        WHERE es_actual = 1;
GO

IF OBJECT_ID(N'dim.estado', N'U') IS NULL
CREATE TABLE dim.estado (
    estado_key  INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dim_estado PRIMARY KEY,
    id_estado   INT           NOT NULL,
    ambito      VARCHAR(30)   NOT NULL,
    codigo      VARCHAR(20)   NOT NULL,
    nombre      VARCHAR(50)   NOT NULL,
    CONSTRAINT UQ_dim_estado_ambito_codigo UNIQUE (ambito, codigo),
    CONSTRAINT UQ_dim_estado_id UNIQUE (id_estado)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_dim_estado_id'
               AND object_id = OBJECT_ID(N'dim.estado'))
    CREATE UNIQUE INDEX UQ_dim_estado_id ON dim.estado (id_estado);
GO

IF OBJECT_ID(N'dim.tipo_movimiento', N'U') IS NULL
CREATE TABLE dim.tipo_movimiento (
    tipo_movimiento_key INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dim_tipo_movimiento PRIMARY KEY,
    id_tipo_movimiento  INT           NOT NULL,
    nombre              VARCHAR(50)   NOT NULL,
    efecto              VARCHAR(20)   NOT NULL,
    CONSTRAINT UQ_dim_tipo_movimiento UNIQUE (id_tipo_movimiento)
);
GO

IF OBJECT_ID(N'dim.tipo_pago', N'U') IS NULL
CREATE TABLE dim.tipo_pago (
    tipo_pago_key INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dim_tipo_pago PRIMARY KEY,
    id_tipo_pago  INT           NOT NULL,
    nombre        VARCHAR(50)   NOT NULL,
    metodo_pago   VARCHAR(50)   NULL,
    banco         VARCHAR(80)   NULL,
    requiere_banco BIT          NULL,
    estado        BIT           NOT NULL,
    CONSTRAINT UQ_dim_tipo_pago UNIQUE (id_tipo_pago)
);
GO

IF OBJECT_ID(N'dim.factura', N'U') IS NULL
CREATE TABLE dim.factura (
    factura_key       INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_dim_factura PRIMARY KEY,
    id_cuenta_pagar   INT           NOT NULL,
    id_proveedor      INT           NOT NULL,
    numero_factura    VARCHAR(50)   NOT NULL,
    fecha_emision     DATE          NOT NULL,
    fecha_vencimiento DATE          NOT NULL,
    CONSTRAINT UQ_dim_factura UNIQUE (id_cuenta_pagar)
);
GO

/* -------------------------------------------------------------------------------------
   HECHOS
   ------------------------------------------------------------------------------------- */
IF OBJECT_ID(N'fact.facturas', N'U') IS NULL
CREATE TABLE fact.facturas (
    factura_key            INT           NOT NULL CONSTRAINT PK_fact_facturas PRIMARY KEY,
    id_cuenta_pagar        INT           NOT NULL,
    tiempo_emision_key     INT           NOT NULL,
    tiempo_vencimiento_key INT           NOT NULL,
    proveedor_key          INT           NOT NULL,
    estado_factura_key     INT           NOT NULL,
    monto_subtotal         DECIMAL(18,2) NOT NULL,
    monto_impuesto         DECIMAL(18,2) NOT NULL,
    monto_total            DECIMAL(18,2) NOT NULL,
    monto_pagado           DECIMAL(18,2) NOT NULL,
    monto_descuento        DECIMAL(18,2) NOT NULL,
    monto_nota_credito     DECIMAL(18,2) NOT NULL,
    monto_nota_debito      DECIMAL(18,2) NOT NULL,
    saldo_pendiente        DECIMAL(18,2) NOT NULL,
    dias_vencido           INT           NOT NULL,
    cantidad_lineas        INT           NOT NULL,
    fecha_ultima_carga     DATETIME2     NOT NULL,
    CONSTRAINT UQ_fact_facturas_cuenta UNIQUE (id_cuenta_pagar),
    CONSTRAINT FK_fact_facturas_dim_factura FOREIGN KEY (factura_key) REFERENCES dim.factura (factura_key),
    CONSTRAINT FK_fact_facturas_tiempo_emision FOREIGN KEY (tiempo_emision_key) REFERENCES dim.tiempo (tiempo_key),
    CONSTRAINT FK_fact_facturas_tiempo_vencimiento FOREIGN KEY (tiempo_vencimiento_key) REFERENCES dim.tiempo (tiempo_key),
    CONSTRAINT FK_fact_facturas_proveedor FOREIGN KEY (proveedor_key) REFERENCES dim.proveedor (proveedor_key),
    CONSTRAINT FK_fact_facturas_estado FOREIGN KEY (estado_factura_key) REFERENCES dim.estado (estado_key),
    CONSTRAINT CK_fact_facturas_saldo CHECK (saldo_pendiente >= 0)
);
GO

IF OBJECT_ID(N'fact.pagos', N'U') IS NULL
CREATE TABLE fact.pagos (
    pago_key                BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_fact_pagos PRIMARY KEY,
    id_movimiento           INT           NOT NULL,
    factura_key             INT           NOT NULL,
    tiempo_pago_key         INT           NOT NULL,
    tiempo_aprobacion_key   INT           NOT NULL,
    proveedor_key           INT           NOT NULL,
    estado_movimiento_key   INT           NOT NULL,
    tipo_movimiento_key     INT           NOT NULL,
    tipo_pago_key           INT           NOT NULL,
    monto_movimiento        DECIMAL(18,2) NOT NULL,
    monto_pago              DECIMAL(18,2) NOT NULL,
    monto_descuento         DECIMAL(18,2) NOT NULL,
    monto_nota_credito      DECIMAL(18,2) NOT NULL,
    monto_nota_debito       DECIMAL(18,2) NOT NULL,
    dias_desde_emision      INT           NULL,
    dias_anticipacion       INT           NULL,
    conciliado              BIT           NOT NULL,
    id_lote                 INT           NULL,
    id_programacion         INT           NULL,
    fecha_ultima_carga      DATETIME2     NOT NULL,
    CONSTRAINT UQ_fact_pagos_movimiento UNIQUE (id_movimiento),
    CONSTRAINT FK_fact_pagos_dim_factura FOREIGN KEY (factura_key) REFERENCES dim.factura (factura_key),
    CONSTRAINT FK_fact_pagos_tiempo_pago FOREIGN KEY (tiempo_pago_key) REFERENCES dim.tiempo (tiempo_key),
    CONSTRAINT FK_fact_pagos_tiempo_aprobacion FOREIGN KEY (tiempo_aprobacion_key) REFERENCES dim.tiempo (tiempo_key),
    CONSTRAINT FK_fact_pagos_proveedor FOREIGN KEY (proveedor_key) REFERENCES dim.proveedor (proveedor_key),
    CONSTRAINT FK_fact_pagos_estado FOREIGN KEY (estado_movimiento_key) REFERENCES dim.estado (estado_key),
    CONSTRAINT FK_fact_pagos_tipo_movimiento FOREIGN KEY (tipo_movimiento_key) REFERENCES dim.tipo_movimiento (tipo_movimiento_key),
    CONSTRAINT FK_fact_pagos_tipo_pago FOREIGN KEY (tipo_pago_key) REFERENCES dim.tipo_pago (tipo_pago_key)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_fact_pagos_tiempo'
               AND object_id = OBJECT_ID(N'fact.pagos'))
    CREATE INDEX IX_fact_pagos_tiempo ON fact.pagos (tiempo_pago_key);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_fact_pagos_proveedor'
               AND object_id = OBJECT_ID(N'fact.pagos'))
    CREATE INDEX IX_fact_pagos_proveedor ON fact.pagos (proveedor_key);
GO

IF OBJECT_ID(N'fact.aging_snapshot', N'U') IS NULL
CREATE TABLE fact.aging_snapshot (
    tiempo_key          INT           NOT NULL,
    factura_key         INT           NOT NULL,
    proveedor_key       INT           NOT NULL,
    estado_factura_key  INT           NOT NULL,
    saldo_pendiente     DECIMAL(18,2) NOT NULL,
    dias_vencido        INT           NOT NULL,
    bucket_antiguedad   VARCHAR(10)   NOT NULL,
    monto_total         DECIMAL(18,2) NOT NULL,
    monto_pagado        DECIMAL(18,2) NOT NULL,
    fecha_ultima_carga  DATETIME2     NOT NULL,
    CONSTRAINT PK_fact_aging_snapshot PRIMARY KEY (tiempo_key, factura_key),
    CONSTRAINT FK_fact_aging_tiempo FOREIGN KEY (tiempo_key) REFERENCES dim.tiempo (tiempo_key),
    CONSTRAINT FK_fact_aging_factura FOREIGN KEY (factura_key) REFERENCES dim.factura (factura_key),
    CONSTRAINT FK_fact_aging_proveedor FOREIGN KEY (proveedor_key) REFERENCES dim.proveedor (proveedor_key),
    CONSTRAINT FK_fact_aging_estado FOREIGN KEY (estado_factura_key) REFERENCES dim.estado (estado_key),
    CONSTRAINT CK_fact_aging_saldo CHECK (saldo_pendiente >= 0)
);
GO

/* -------------------------------------------------------------------------------------
   CONTROL ETL
   ------------------------------------------------------------------------------------- */
IF OBJECT_ID(N'etl.ejecucion', N'U') IS NULL
CREATE TABLE etl.ejecucion (
    id_ejecucion       BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_etl_ejecucion PRIMARY KEY,
    proceso            VARCHAR(100)  NOT NULL,
    fecha_inicio       DATETIME2     NOT NULL CONSTRAINT DF_etl_ejecucion_inicio DEFAULT (SYSDATETIME()),
    fecha_fin          DATETIME2     NULL,
    filas_leidas       INT           NULL,
    filas_insertadas   INT           NULL,
    filas_actualizadas INT           NULL,
    estado             VARCHAR(20)   NOT NULL CONSTRAINT DF_etl_ejecucion_estado DEFAULT ('EN_PROCESO'),
    mensaje_error      NVARCHAR(2000) NULL,
    CONSTRAINT CK_etl_ejecucion_estado CHECK (estado IN ('EN_PROCESO','OK','ERROR'))
);
GO

IF OBJECT_ID(N'etl.error', N'U') IS NULL
CREATE TABLE etl.error (
    id_error     BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_etl_error PRIMARY KEY,
    id_ejecucion BIGINT        NULL,
    proceso      VARCHAR(100)  NOT NULL,
    mensaje      NVARCHAR(2000) NOT NULL,
    fecha        DATETIME2     NOT NULL CONSTRAINT DF_etl_error_fecha DEFAULT (SYSDATETIME()),
    CONSTRAINT FK_etl_error_ejecucion FOREIGN KEY (id_ejecucion) REFERENCES etl.ejecucion (id_ejecucion)
);
GO

/* -------------------------------------------------------------------------------------
   Miembros desconocidos (clave 0) para todas las dimensiones.
   ------------------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM dim.tiempo WHERE tiempo_key = 0)
    INSERT INTO dim.tiempo (tiempo_key, fecha, anio, trimestre, mes, nombre_mes, anio_mes, semana, dia, dia_semana, nombre_dia, es_fin_semana, es_feriado)
    VALUES (0, '1900-01-01', 1900, 1, 1, 'Desconocido', '1900-01', 1, 1, 1, 'Desconocido', 0, 0);
GO

IF NOT EXISTS (SELECT 1 FROM dim.proveedor WHERE proveedor_key = 0)
BEGIN
    SET IDENTITY_INSERT dim.proveedor ON;
    INSERT INTO dim.proveedor (proveedor_key, id_proveedor, identificacion_tributaria, razon_social, pais,
        dias_credito, banco, cuenta_bancaria, estado, fecha_inicio_validez, fecha_fin_validez, es_actual, version, hash_atributos)
    VALUES (0, -1, 'DESCONOCIDO', 'DESCONOCIDO', NULL, NULL, NULL, NULL, 0,
        '1900-01-01', NULL, 1, 0, REPLICATE('0', 64));
    SET IDENTITY_INSERT dim.proveedor OFF;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dim.estado WHERE estado_key = 0)
BEGIN
    SET IDENTITY_INSERT dim.estado ON;
    INSERT INTO dim.estado (estado_key, id_estado, ambito, codigo, nombre)
    VALUES (0, -1, 'DESCONOCIDO', 'DESCONOCIDO', 'DESCONOCIDO');
    SET IDENTITY_INSERT dim.estado OFF;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dim.tipo_movimiento WHERE tipo_movimiento_key = 0)
BEGIN
    SET IDENTITY_INSERT dim.tipo_movimiento ON;
    INSERT INTO dim.tipo_movimiento (tipo_movimiento_key, id_tipo_movimiento, nombre, efecto)
    VALUES (0, -1, 'DESCONOCIDO', 'DESCONOCIDO');
    SET IDENTITY_INSERT dim.tipo_movimiento OFF;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dim.tipo_pago WHERE tipo_pago_key = 0)
BEGIN
    SET IDENTITY_INSERT dim.tipo_pago ON;
    INSERT INTO dim.tipo_pago (tipo_pago_key, id_tipo_pago, nombre, metodo_pago, banco, requiere_banco, estado)
    VALUES (0, -1, 'DESCONOCIDO', NULL, NULL, NULL, 0);
    SET IDENTITY_INSERT dim.tipo_pago OFF;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dim.factura WHERE factura_key = 0)
BEGIN
    SET IDENTITY_INSERT dim.factura ON;
    INSERT INTO dim.factura (factura_key, id_cuenta_pagar, id_proveedor, numero_factura, fecha_emision, fecha_vencimiento)
    VALUES (0, -1, -1, 'DESCONOCIDO', '1900-01-01', '1900-01-01');
    SET IDENTITY_INSERT dim.factura OFF;
END;
GO
