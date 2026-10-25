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
   - No permite pagar antes de la fecha de emisión.
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
