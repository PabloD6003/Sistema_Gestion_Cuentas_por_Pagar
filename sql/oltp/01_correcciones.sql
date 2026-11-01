/* =====================================================================================
   Correcciones y extensiones sobre el script base del OLTP.
   Ejecutar después de 00_base.sql y antes de 02_lectura.sql.
   ===================================================================================== */
USE CuentasPorPagarDB;
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/* -------------------------------------------------------------------------------------
   1. Tipos de pago: permitir el mismo nombre en bancos distintos.
   ------------------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.key_constraints
           WHERE name = 'UQ_CP03_nombre'
             AND parent_object_id = OBJECT_ID('dbo.CP03_tipos_pago'))
    ALTER TABLE dbo.CP03_tipos_pago DROP CONSTRAINT UQ_CP03_nombre;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UQ_CP03_banco_nombre'
                 AND object_id = OBJECT_ID('dbo.CP03_tipos_pago'))
    CREATE UNIQUE INDEX UQ_CP03_banco_nombre
        ON dbo.CP03_tipos_pago (id_banco, nombre)
        WHERE id_banco IS NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UQ_CP03_sin_banco_nombre'
                 AND object_id = OBJECT_ID('dbo.CP03_tipos_pago'))
    CREATE UNIQUE INDEX UQ_CP03_sin_banco_nombre
        ON dbo.CP03_tipos_pago (nombre)
        WHERE id_banco IS NULL;
GO

/* -------------------------------------------------------------------------------------
   2. Vínculo pago -> descuento.
   El descuento se registra como un movimiento hijo del pago para poder reversarlo.
   ------------------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.CP07_movimientos', 'id_movimiento_origen') IS NULL
    ALTER TABLE dbo.CP07_movimientos ADD id_movimiento_origen INT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
               WHERE name = 'FK_CP07_movimiento_origen'
                 AND parent_object_id = OBJECT_ID('dbo.CP07_movimientos'))
    ALTER TABLE dbo.CP07_movimientos
        ADD CONSTRAINT FK_CP07_movimiento_origen
        FOREIGN KEY (id_movimiento_origen) REFERENCES dbo.CP07_movimientos (id_movimiento);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UQ_CP07_movimiento_origen'
                 AND object_id = OBJECT_ID('dbo.CP07_movimientos'))
    CREATE UNIQUE INDEX UQ_CP07_movimiento_origen
        ON dbo.CP07_movimientos (id_movimiento_origen)
        WHERE id_movimiento_origen IS NOT NULL;
GO

/* -------------------------------------------------------------------------------------
   3. Registro de movimientos.
   - Un movimiento de tipo "Pago" exige método de pago.
   - No permite pagar antes de la fecha de emisión ni con fecha futura.
   - Guarda el movimiento origen (para el descuento).
   ------------------------------------------------------------------------------------- */
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
    @id_movimiento_origen INT = NULL,
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

        DECLARE @estado_cuenta VARCHAR(20), @efecto VARCHAR(20), @tipo_nombre VARCHAR(50),
                @fecha_emision DATE;

        SELECT @estado_cuenta = e.codigo, @fecha_emision = c.fecha_emision
        FROM dbo.CP04_cuentas_por_pagar c WITH (UPDLOCK, HOLDLOCK)
        JOIN dbo.CP08_estados e ON e.id_estado = c.id_estado
        WHERE c.id_cuenta_pagar = @id_cuenta_pagar;

        SELECT @efecto = e.codigo, @tipo_nombre = t.nombre
        FROM dbo.CP02_tipos_movimiento t
        JOIN dbo.CP08_estados e ON e.id_estado = t.id_efecto
        WHERE t.id_tipo_movimiento = @id_tipo_movimiento AND t.estado = 1;

        IF @estado_cuenta IS NULL
            THROW 50040, 'La cuenta por pagar no existe.', 1;
        IF @estado_cuenta = 'ANULADA'
            THROW 50041, 'La cuenta por pagar está anulada.', 1;
        IF @efecto IS NULL
            THROW 50042, 'El tipo de movimiento no existe o está inactivo.', 1;
        IF @monto <= 0
            THROW 50043, 'El monto debe ser mayor que cero.', 1;
        IF @fecha_movimiento < @fecha_emision
            THROW 50046, 'La fecha del movimiento no puede ser anterior a la emisión de la factura.', 1;
        IF @fecha_movimiento > CAST(SYSDATETIME() AS DATE)
            THROW 50049, 'La fecha del movimiento no puede ser posterior a hoy.', 1;
        IF @efecto = 'DISMINUYE' AND @monto > dbo.fn_saldo_disponible(@id_cuenta_pagar)
            THROW 50044, 'El monto supera el saldo disponible de la cuenta.', 1;
        IF @tipo_nombre = 'Pago' AND @id_tipo_pago IS NULL
            THROW 50047, 'Un pago requiere tipo de pago.', 1;
        IF @id_tipo_pago IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM dbo.CP03_tipos_pago WHERE id_tipo_pago = @id_tipo_pago AND estado = 1)
            THROW 50045, 'El tipo de pago no existe o está inactivo.', 1;
        IF @id_movimiento_origen IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM dbo.CP07_movimientos
                           WHERE id_movimiento = @id_movimiento_origen
                             AND id_cuenta_pagar = @id_cuenta_pagar)
            THROW 50048, 'El movimiento origen no pertenece a la misma cuenta.', 1;

        INSERT INTO dbo.CP07_movimientos (id_cuenta_pagar, id_tipo_movimiento, numero_movimiento, id_tipo_pago,
                    id_programacion, fecha_movimiento, monto, referencia, descripcion, id_estado,
                    id_usuario_registro, codigo_erp, id_movimiento_origen)
        SELECT @id_cuenta_pagar, @id_tipo_movimiento, ISNULL(MAX(numero_movimiento), 0) + 1, @id_tipo_pago,
               @id_programacion, @fecha_movimiento, @monto, @referencia, @descripcion,
               dbo.fn_estado_id('MOVIMIENTO', 'REGISTRADO'), @id_usuario, @codigo_erp, @id_movimiento_origen
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

/* -------------------------------------------------------------------------------------
   4. Registro de pago con descuento vinculado.
   ------------------------------------------------------------------------------------- */
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
                 @descripcion = 'Descuento por pronto pago', @id_movimiento_origen = @id_movimiento,
                 @id_usuario = @id_usuario, @id_movimiento = @id_desc OUTPUT;

        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_pago_registrar;
        THROW;
    END CATCH;
END;
GO

/* -------------------------------------------------------------------------------------
   5. Anulación de movimientos.
   - No permite anular movimientos dentro de un lote.
   - Si el movimiento tiene un descuento hijo, obliga a usar sp_pago_anular.
   ------------------------------------------------------------------------------------- */
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
        IF @estado_lote IS NOT NULL
            THROW 50074, 'El movimiento pertenece a un lote. Rechace el lote antes de anular.', 1;
        IF EXISTS (SELECT 1 FROM dbo.CP07_movimientos
                   WHERE id_movimiento_origen = @id_movimiento
                     AND id_estado <> dbo.fn_estado_id('MOVIMIENTO', 'ANULADO'))
            THROW 50076, 'El pago tiene un descuento asociado. Use sp_pago_anular.', 1;

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

/* -------------------------------------------------------------------------------------
   6. Anulación atómica de un pago y su descuento.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_pago_anular
    @id_movimiento INT,
    @motivo        VARCHAR(150),
    @id_usuario    INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;
    IF dbo.fn_usuario_puede_aprobar(@id_usuario) = 0
        THROW 50090, 'El rol del usuario no permite anular pagos.', 1;

    DECLARE @tc INT = @@TRANCOUNT;
    BEGIN TRY
        IF @tc = 0 BEGIN TRANSACTION; ELSE SAVE TRANSACTION sp_pago_anular;

        DECLARE @id_cuenta INT, @monto DECIMAL(18,2), @estado VARCHAR(20), @efecto VARCHAR(20),
                @conciliado BIT, @estado_lote VARCHAR(20), @tipo VARCHAR(50),
                @nuevo_saldo DECIMAL(18,2), @monto_total DECIMAL(18,2), @fecha_vencimiento DATE,
                @id_desc INT, @monto_desc DECIMAL(18,2), @estado_desc VARCHAR(20), @efecto_desc VARCHAR(20);

        SELECT @id_cuenta = m.id_cuenta_pagar, @monto = m.monto, @estado = em.codigo,
               @efecto = ef.codigo, @conciliado = m.conciliado, @estado_lote = el.codigo,
               @tipo = t.nombre
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP08_estados em         ON em.id_estado = m.id_estado
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        LEFT JOIN dbo.CP16_lotes_transferencia l ON l.id_lote = m.id_lote
        LEFT JOIN dbo.CP08_estados el            ON el.id_estado = l.id_estado
        WHERE m.id_movimiento = @id_movimiento;

        IF @id_cuenta IS NULL THROW 50091, 'El movimiento no existe.', 1;
        IF @tipo <> 'Pago' THROW 50092, 'El movimiento no es un pago.', 1;
        IF @estado = 'ANULADO' THROW 50093, 'El pago ya está anulado.', 1;
        IF @conciliado = 1 THROW 50094, 'No se puede anular un pago conciliado con el banco.', 1;
        IF @estado_lote IS NOT NULL
            THROW 50095, 'El pago pertenece a un lote. Rechace el lote antes de anular.', 1;

        SELECT @id_desc = m.id_movimiento, @monto_desc = m.monto,
               @estado_desc = em.codigo, @efecto_desc = ef.codigo
        FROM dbo.CP07_movimientos m
        JOIN dbo.CP08_estados em         ON em.id_estado = m.id_estado
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        WHERE m.id_movimiento_origen = @id_movimiento;

        SELECT @nuevo_saldo = c.saldo_pendiente, @monto_total = c.monto_total,
               @fecha_vencimiento = c.fecha_vencimiento
        FROM dbo.CP04_cuentas_por_pagar c WITH (UPDLOCK, HOLDLOCK)
        WHERE c.id_cuenta_pagar = @id_cuenta;

        IF @nuevo_saldo IS NULL THROW 50096, 'La cuenta por pagar no existe.', 1;

        IF @estado = 'APROBADO'
            SET @nuevo_saldo = IIF(@efecto = 'DISMINUYE', @nuevo_saldo + @monto, @nuevo_saldo - @monto);

        IF @id_desc IS NOT NULL AND @estado_desc = 'APROBADO'
            SET @nuevo_saldo = IIF(@efecto_desc = 'DISMINUYE', @nuevo_saldo + @monto_desc, @nuevo_saldo - @monto_desc);

        IF @nuevo_saldo < 0 THROW 50097, 'Anular el pago deja el saldo en negativo.', 1;

        UPDATE dbo.CP04_cuentas_por_pagar
        SET saldo_pendiente = @nuevo_saldo,
            id_estado = dbo.fn_estado_id('CUENTA_PAGAR',
                        dbo.fn_estado_cuenta_codigo(@nuevo_saldo, @monto_total, @fecha_vencimiento, CAST(SYSDATETIME() AS DATE)))
        WHERE id_cuenta_pagar = @id_cuenta;

        IF @id_desc IS NOT NULL
        BEGIN
            UPDATE dbo.CP07_movimientos
            SET id_estado = dbo.fn_estado_id('MOVIMIENTO', 'ANULADO'), id_lote = NULL
            WHERE id_movimiento = @id_desc;

            EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_desc, 'ANULAR',
                 NULL, 'Anulado junto con el pago';
        END;

        UPDATE dbo.CP07_movimientos
        SET id_estado = dbo.fn_estado_id('MOVIMIENTO', 'ANULADO'), id_lote = NULL
        WHERE id_movimiento = @id_movimiento;

        EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_movimiento, 'ANULAR', NULL, @motivo;
        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_pago_anular;
        THROW;
    END CATCH;
END;
GO

/* -------------------------------------------------------------------------------------
   7. Generación de lotes: excluir pagos ya conciliados.
   ------------------------------------------------------------------------------------- */
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
          AND m.conciliado = 0
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

/* -------------------------------------------------------------------------------------
   8. Estados del lote: permitir cancelar un lote GENERADO.
   ------------------------------------------------------------------------------------- */
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
    IF NOT ((@actual = 'GENERADO' AND @codigo_estado IN ('ENVIADO', 'RECHAZADO'))
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

/* -------------------------------------------------------------------------------------
   9. Registro de cuentas por pagar.
   - No permite fecha de emisión futura.
   - Los días de crédito de la factura respetan el mismo rango que el proveedor (0 a 365).
   ------------------------------------------------------------------------------------- */
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
    IF @fecha_emision > CAST(SYSDATETIME() AS DATE)
        THROW 50024, 'La fecha de emisión no puede ser posterior a hoy.', 1;

    SELECT @dias_credito = ISNULL(@dias_credito, dias_credito)
    FROM dbo.CP01_proveedores WHERE id_proveedor = @id_proveedor;
    IF @dias_credito NOT BETWEEN 0 AND 365
        THROW 50025, 'Los días de crédito deben estar entre 0 y 365.', 1;
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

/* -------------------------------------------------------------------------------------
   10. Aprobación de movimientos.
   - El descuento por pronto pago no se aprueba por separado antes que su pago: al aprobar
     el pago se aprueba también su descuento pendiente, en la misma transacción.
   - Un descuento suelto (de datos anteriores) solo se aprueba si su pago ya está aprobado.
   ------------------------------------------------------------------------------------- */
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
                @registra INT, @tipo VARCHAR(50), @id_origen INT, @estado_origen VARCHAR(20),
                @id_desc INT, @monto_desc DECIMAL(18,2), @efecto_desc VARCHAR(20), @registra_desc INT,
                @saldo DECIMAL(18,2), @nuevo_saldo DECIMAL(18,2);

        SELECT @id_cuenta = m.id_cuenta_pagar, @monto = m.monto, @estado = em.codigo,
               @efecto = ef.codigo, @registra = m.id_usuario_registro, @tipo = t.nombre,
               @id_origen = m.id_movimiento_origen
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP08_estados em         ON em.id_estado = m.id_estado
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        WHERE m.id_movimiento = @id_movimiento;

        IF @id_cuenta IS NULL THROW 50061, 'El movimiento no existe.', 1;
        IF @estado <> 'REGISTRADO' THROW 50062, 'Solo se pueden aprobar movimientos en estado REGISTRADO.', 1;
        IF @registra = @id_usuario THROW 50063, 'Quien registró el movimiento no puede aprobarlo.', 1;

        IF @id_origen IS NOT NULL
        BEGIN
            SELECT @estado_origen = e.codigo
            FROM dbo.CP07_movimientos m
            JOIN dbo.CP08_estados e ON e.id_estado = m.id_estado
            WHERE m.id_movimiento = @id_origen;

            IF @estado_origen <> 'APROBADO'
                THROW 50065, 'El descuento se aprueba junto con su pago; apruebe primero el pago.', 1;
        END;

        SELECT @id_desc = m.id_movimiento, @monto_desc = m.monto, @efecto_desc = ef.codigo,
               @registra_desc = m.id_usuario_registro
        FROM dbo.CP07_movimientos m WITH (UPDLOCK)
        JOIN dbo.CP02_tipos_movimiento t ON t.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dbo.CP08_estados ef         ON ef.id_estado = t.id_efecto
        WHERE m.id_movimiento_origen = @id_movimiento
          AND m.id_estado = dbo.fn_estado_id('MOVIMIENTO', 'REGISTRADO');

        IF @registra_desc = @id_usuario THROW 50063, 'Quien registró el movimiento no puede aprobarlo.', 1;

        SELECT @saldo = saldo_pendiente FROM dbo.CP04_cuentas_por_pagar WITH (UPDLOCK, HOLDLOCK)
        WHERE id_cuenta_pagar = @id_cuenta;

        SET @nuevo_saldo = IIF(@efecto = 'DISMINUYE', @saldo - @monto, @saldo + @monto);
        IF @id_desc IS NOT NULL
            SET @nuevo_saldo = IIF(@efecto_desc = 'DISMINUYE', @nuevo_saldo - @monto_desc, @nuevo_saldo + @monto_desc);
        IF @nuevo_saldo < 0 THROW 50064, 'El movimiento deja el saldo de la cuenta en negativo.', 1;

        UPDATE dbo.CP07_movimientos
        SET id_estado = dbo.fn_estado_id('MOVIMIENTO', 'APROBADO'),
            id_usuario_aprueba = @id_usuario, fecha_aprobacion = SYSDATETIME()
        WHERE id_movimiento IN (@id_movimiento, @id_desc);

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
        IF @id_desc IS NOT NULL
            EXEC dbo.sp_auditoria_registrar @id_usuario, 'CP07_movimientos', @id_desc, 'APROBAR',
                 NULL, 'Aprobado junto con el pago';
        IF @tc = 0 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF XACT_STATE() = -1 OR (XACT_STATE() = 1 AND @tc = 0) ROLLBACK TRANSACTION;
        ELSE IF XACT_STATE() = 1 ROLLBACK TRANSACTION sp_mov_aprobar;
        THROW;
    END CATCH;
END;
GO

/* -------------------------------------------------------------------------------------
   11. Respaldos desde la aplicación (R6).
   - Tipos: COMPLETO, DIFERENCIAL (cambios desde el último completo) y LOG (incremental
     del registro de transacciones; requiere modelo de recuperación FULL).
   - Sin @ruta se usa la carpeta de respaldos configurada en la instancia.
   - Cada respaldo se verifica con RESTORE VERIFYONLY y queda en la bitácora de auditoría.
   - La cuenta con la que se conecta la aplicación necesita permiso de BACKUP
     (db_backupoperator) y CREATE DATABASE para la verificación.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_respaldo_base
    @tipo       VARCHAR(12) = 'COMPLETO',
    @ruta       NVARCHAR(200) = NULL,
    @id_usuario INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC dbo.sp_contexto_usuario @id_usuario;

    IF NOT EXISTS (SELECT 1 FROM dbo.CP12_usuarios u
                   JOIN dbo.CP11_roles r ON r.id_rol = u.id_rol
                   WHERE u.id_usuario = @id_usuario AND r.nivel_acceso >= 5)
        THROW 50130, 'Solo un administrador puede generar respaldos.', 1;
    IF @tipo IS NULL OR @tipo NOT IN ('COMPLETO', 'DIFERENCIAL', 'LOG')
        THROW 50131, 'El tipo de respaldo debe ser COMPLETO, DIFERENCIAL o LOG.', 1;

    DECLARE @base SYSNAME = DB_NAME();

    -- El diferencial necesita un respaldo completo previo de esta base
    IF @tipo = 'DIFERENCIAL'
       AND (SELECT differential_base_lsn FROM sys.database_files WHERE file_id = 1) IS NULL
        THROW 50132, 'Primero debe generar un respaldo completo de la base.', 1;
    IF @tipo = 'LOG' AND CAST(DATABASEPROPERTYEX(@base, 'Recovery') AS VARCHAR(20)) = 'SIMPLE'
        THROW 50133, 'La base usa el modelo de recuperación SIMPLE y no admite respaldos de log.', 1;
    -- Sin respaldo completo previo el log todavía no tiene cadena de respaldos
    IF @tipo = 'LOG'
       AND (SELECT last_log_backup_lsn FROM sys.database_recovery_status WHERE database_id = DB_ID()) IS NULL
        THROW 50132, 'Primero debe generar un respaldo completo de la base.', 1;

    SET @ruta = ISNULL(NULLIF(LTRIM(RTRIM(@ruta)), ''),
                       CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS NVARCHAR(200)));

    DECLARE @archivo NVARCHAR(260) = CONCAT(@ruta, IIF(RIGHT(@ruta, 1) = '\', '', '\'), @base, '_',
            CASE @tipo WHEN 'COMPLETO' THEN 'FULL' WHEN 'DIFERENCIAL' THEN 'DIFF' ELSE 'LOG' END, '_',
            FORMAT(SYSDATETIME(), 'yyyyMMdd_HHmmss_fff'), IIF(@tipo = 'LOG', '.trn', '.bak'));

    IF @tipo = 'COMPLETO'
        BACKUP DATABASE @base TO DISK = @archivo WITH INIT, CHECKSUM;
    ELSE IF @tipo = 'DIFERENCIAL'
        BACKUP DATABASE @base TO DISK = @archivo WITH DIFFERENTIAL, INIT, CHECKSUM;
    ELSE
        BACKUP LOG @base TO DISK = @archivo WITH INIT, CHECKSUM;

    RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM;

    EXEC dbo.sp_auditoria_registrar @id_usuario, 'BASE_DE_DATOS', @tipo, 'RESPALDO', NULL, @archivo;

    SELECT TOP (1) @tipo AS tipo, @archivo AS archivo, b.backup_finish_date AS fecha,
           CAST(b.backup_size / 1048576.0 AS DECIMAL(12,2)) AS tamano_mb
    FROM msdb.dbo.backupset b
    JOIN msdb.dbo.backupmediafamily f ON f.media_set_id = b.media_set_id
    WHERE b.database_name = @base AND f.physical_device_name = @archivo
    ORDER BY b.backup_set_id DESC;
END;
GO

-- Historial de respaldos de la base (msdb) con el usuario de la aplicación que lo generó
CREATE OR ALTER PROCEDURE dbo.sp_respaldos_listar
    @maximo INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP (@maximo)
           b.backup_set_id AS id_respaldo,
           CASE b.type WHEN 'D' THEN 'COMPLETO' WHEN 'I' THEN 'DIFERENCIAL' WHEN 'L' THEN 'LOG' ELSE b.type END AS tipo,
           b.backup_start_date AS fecha_inicio,
           b.backup_finish_date AS fecha_fin,
           CAST(b.backup_size / 1048576.0 AS DECIMAL(12,2)) AS tamano_mb,
           f.physical_device_name AS archivo,
           u.nombre_usuario AS generado_por
    FROM msdb.dbo.backupset b
    JOIN msdb.dbo.backupmediafamily f ON f.media_set_id = b.media_set_id
    OUTER APPLY (SELECT TOP (1) a.id_usuario FROM dbo.CP09_auditoria_acciones a
                 WHERE a.accion = 'RESPALDO' AND a.valor_nuevo = f.physical_device_name
                 ORDER BY a.id_auditoria DESC) a
    LEFT JOIN dbo.CP12_usuarios u ON u.id_usuario = a.id_usuario
    WHERE b.database_name = DB_NAME()
    ORDER BY b.backup_finish_date DESC, b.backup_set_id DESC;
END;
GO

/* -------------------------------------------------------------------------------------
   12. Permisos de los procedimientos agregados en este script.
   ------------------------------------------------------------------------------------- */
GRANT EXECUTE ON dbo.sp_pago_anular TO rol_cxp_tesoreria;
GO
