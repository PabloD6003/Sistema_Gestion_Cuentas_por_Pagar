/* =====================================================================================
   ETL del Data Warehouse.
   Origen: CuentasPorPagarDB (OLTP).
   Destino: CuentasPorPagarDW.
   Ejecutar después de 01_schema_dw.sql y 02_dim_tiempo.sql.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
GO

/* -------------------------------------------------------------------------------------
   Dimensión estado.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_dim_estado
AS
BEGIN
    SET NOCOUNT ON;

    MERGE dim.estado AS d
    USING (
        SELECT id_estado, ambito, codigo, nombre
        FROM CuentasPorPagarDB.dbo.CP08_estados
    ) AS s
       ON d.id_estado = s.id_estado
    WHEN MATCHED AND (d.ambito <> s.ambito OR d.codigo <> s.codigo OR d.nombre <> s.nombre) THEN
        UPDATE SET ambito = s.ambito, codigo = s.codigo, nombre = s.nombre
    WHEN NOT MATCHED THEN
        INSERT (id_estado, ambito, codigo, nombre)
        VALUES (s.id_estado, s.ambito, s.codigo, s.nombre);
END;
GO

/* -------------------------------------------------------------------------------------
   Dimensión tipo de movimiento.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_dim_tipo_movimiento
AS
BEGIN
    SET NOCOUNT ON;

    MERGE dim.tipo_movimiento AS d
    USING (
        SELECT t.id_tipo_movimiento, t.nombre, e.codigo AS efecto
        FROM CuentasPorPagarDB.dbo.CP02_tipos_movimiento t
        JOIN CuentasPorPagarDB.dbo.CP08_estados e ON e.id_estado = t.id_efecto
    ) AS s
       ON d.id_tipo_movimiento = s.id_tipo_movimiento
    WHEN MATCHED AND (d.nombre <> s.nombre OR d.efecto <> s.efecto) THEN
        UPDATE SET nombre = s.nombre, efecto = s.efecto
    WHEN NOT MATCHED THEN
        INSERT (id_tipo_movimiento, nombre, efecto)
        VALUES (s.id_tipo_movimiento, s.nombre, s.efecto);
END;
GO

/* -------------------------------------------------------------------------------------
   Dimensión tipo de pago.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_dim_tipo_pago
AS
BEGIN
    SET NOCOUNT ON;

    MERGE dim.tipo_pago AS d
    USING (
        SELECT tp.id_tipo_pago, tp.nombre, mp.nombre AS metodo_pago,
               b.nombre_banco AS banco, mp.requiere_banco, tp.estado
        FROM CuentasPorPagarDB.dbo.CP03_tipos_pago tp
        JOIN CuentasPorPagarDB.dbo.CP06_metodos_pago mp ON mp.id_metodo_pago = tp.id_metodo_pago
        LEFT JOIN CuentasPorPagarDB.dbo.CP15_bancos b ON b.id_banco = tp.id_banco
    ) AS s
       ON d.id_tipo_pago = s.id_tipo_pago
    WHEN MATCHED AND (d.nombre <> s.nombre OR ISNULL(d.metodo_pago,'') <> ISNULL(s.metodo_pago,'')
                      OR ISNULL(d.banco,'') <> ISNULL(s.banco,'')
                      OR ISNULL(d.requiere_banco,0) <> ISNULL(s.requiere_banco,0)
                      OR d.estado <> s.estado) THEN
        UPDATE SET nombre = s.nombre, metodo_pago = s.metodo_pago, banco = s.banco,
                   requiere_banco = s.requiere_banco, estado = s.estado
    WHEN NOT MATCHED THEN
        INSERT (id_tipo_pago, nombre, metodo_pago, banco, requiere_banco, estado)
        VALUES (s.id_tipo_pago, s.nombre, s.metodo_pago, s.banco, s.requiere_banco, s.estado);
END;
GO

/* -------------------------------------------------------------------------------------
   Dimensión proveedor con SCD Tipo 2.
   Cierra la versión actual cuando cambian los atributos y crea una nueva.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_dim_proveedor
    @fecha_carga DATETIME2 = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_carga = ISNULL(@fecha_carga, SYSDATETIME());

    ;WITH origen AS (
        SELECT p.id_proveedor,
               p.identificacion_tributaria,
               p.razon_social,
               p.pais,
               p.dias_credito,
               p.banco,
               p.cuenta_bancaria,
               p.estado,
               CONVERT(CHAR(64), HASHBYTES('SHA2_256', CONCAT(
                   ISNULL(p.identificacion_tributaria,''), '|',
                   ISNULL(p.razon_social,''), '|',
                   ISNULL(p.pais,''), '|',
                   ISNULL(CAST(p.dias_credito AS VARCHAR(10)),''), '|',
                   ISNULL(p.banco,''), '|',
                   ISNULL(p.cuenta_bancaria,''), '|',
                   ISNULL(CAST(p.estado AS VARCHAR(1)),'')
               )), 2) AS hash_atributos
        FROM CuentasPorPagarDB.dbo.CP01_proveedores p
    )
    UPDATE d
    SET fecha_fin_validez = @fecha_carga,
        es_actual = 0
    FROM dim.proveedor d
    JOIN origen o ON o.id_proveedor = d.id_proveedor
    WHERE d.es_actual = 1
      AND d.hash_atributos <> o.hash_atributos;

    ;WITH origen AS (
        SELECT p.id_proveedor,
               p.identificacion_tributaria,
               p.razon_social,
               p.pais,
               p.dias_credito,
               p.banco,
               p.cuenta_bancaria,
               p.estado,
               CONVERT(CHAR(64), HASHBYTES('SHA2_256', CONCAT(
                   ISNULL(p.identificacion_tributaria,''), '|',
                   ISNULL(p.razon_social,''), '|',
                   ISNULL(p.pais,''), '|',
                   ISNULL(CAST(p.dias_credito AS VARCHAR(10)),''), '|',
                   ISNULL(p.banco,''), '|',
                   ISNULL(p.cuenta_bancaria,''), '|',
                   ISNULL(CAST(p.estado AS VARCHAR(1)),'')
               )), 2) AS hash_atributos
        FROM CuentasPorPagarDB.dbo.CP01_proveedores p
    )
    INSERT INTO dim.proveedor (id_proveedor, identificacion_tributaria, razon_social, pais,
        dias_credito, banco, cuenta_bancaria, estado, fecha_inicio_validez, fecha_fin_validez,
        es_actual, version, hash_atributos)
    SELECT o.id_proveedor, o.identificacion_tributaria, o.razon_social, o.pais,
           o.dias_credito, o.banco, o.cuenta_bancaria, o.estado,
           CASE WHEN EXISTS (SELECT 1 FROM dim.proveedor d WHERE d.id_proveedor = o.id_proveedor)
                THEN @fecha_carga ELSE CAST('1900-01-01' AS DATETIME2) END,
           NULL,
           1,
           ISNULL((SELECT MAX(d.version) FROM dim.proveedor d WHERE d.id_proveedor = o.id_proveedor), 0) + 1,
           o.hash_atributos
    FROM origen o
    WHERE NOT EXISTS (SELECT 1 FROM dim.proveedor d
                      WHERE d.id_proveedor = o.id_proveedor AND d.es_actual = 1);
END;
GO

/* -------------------------------------------------------------------------------------
   Dimensión factura.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_dim_factura
AS
BEGIN
    SET NOCOUNT ON;

    MERGE dim.factura AS d
    USING (
        SELECT id_cuenta_pagar, id_proveedor, numero_factura, fecha_emision, fecha_vencimiento
        FROM CuentasPorPagarDB.dbo.CP04_cuentas_por_pagar
    ) AS s
       ON d.id_cuenta_pagar = s.id_cuenta_pagar
    WHEN MATCHED AND (d.id_proveedor <> s.id_proveedor
                      OR d.numero_factura <> s.numero_factura
                      OR d.fecha_emision <> s.fecha_emision
                      OR d.fecha_vencimiento <> s.fecha_vencimiento) THEN
        UPDATE SET id_proveedor = s.id_proveedor, numero_factura = s.numero_factura,
                   fecha_emision = s.fecha_emision, fecha_vencimiento = s.fecha_vencimiento
    WHEN NOT MATCHED THEN
        INSERT (id_cuenta_pagar, id_proveedor, numero_factura, fecha_emision, fecha_vencimiento)
        VALUES (s.id_cuenta_pagar, s.id_proveedor, s.numero_factura, s.fecha_emision, s.fecha_vencimiento);
END;
GO

/* -------------------------------------------------------------------------------------
   Hecho facturas (acumulativo por factura).
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_fact_facturas
    @fecha_corte DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    ;WITH movimientos AS (
        SELECT m.id_cuenta_pagar,
               SUM(CASE WHEN tm.nombre = 'Pago' AND em.codigo = 'APROBADO' THEN m.monto ELSE 0 END) AS monto_pagado,
               SUM(CASE WHEN tm.nombre = 'Descuento por pronto pago' AND em.codigo = 'APROBADO' THEN m.monto ELSE 0 END) AS monto_descuento,
               SUM(CASE WHEN tm.nombre = 'Nota de crédito' AND em.codigo = 'APROBADO' THEN m.monto ELSE 0 END) AS monto_nota_credito,
               SUM(CASE WHEN tm.nombre = 'Nota de débito' AND em.codigo = 'APROBADO' THEN m.monto ELSE 0 END) AS monto_nota_debito
        FROM CuentasPorPagarDB.dbo.CP07_movimientos m
        JOIN CuentasPorPagarDB.dbo.CP02_tipos_movimiento tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN CuentasPorPagarDB.dbo.CP08_estados em ON em.id_estado = m.id_estado
        GROUP BY m.id_cuenta_pagar
    ),
    lineas AS (
        SELECT id_cuenta_pagar, COUNT(*) AS cantidad_lineas
        FROM CuentasPorPagarDB.dbo.CP05_cuentas_por_pagar_detalle
        GROUP BY id_cuenta_pagar
    ),
    origen AS (
        SELECT c.id_cuenta_pagar,
               f.factura_key,
               CONVERT(INT, CONVERT(CHAR(8), c.fecha_emision, 112)) AS tiempo_emision_key,
               CONVERT(INT, CONVERT(CHAR(8), c.fecha_vencimiento, 112)) AS tiempo_vencimiento_key,
               ISNULL(pv.proveedor_key, 0) AS proveedor_key,
               ISNULL(es.estado_key, 0) AS estado_factura_key,
               c.subtotal,
               c.monto_impuesto,
               c.monto_total,
               ISNULL(mv.monto_pagado, 0) AS monto_pagado,
               ISNULL(mv.monto_descuento, 0) AS monto_descuento,
               ISNULL(mv.monto_nota_credito, 0) AS monto_nota_credito,
               ISNULL(mv.monto_nota_debito, 0) AS monto_nota_debito,
               c.saldo_pendiente,
               CASE WHEN c.saldo_pendiente > 0 AND c.fecha_vencimiento < @fecha_corte
                    THEN DATEDIFF(DAY, c.fecha_vencimiento, @fecha_corte) ELSE 0 END AS dias_vencido,
               ISNULL(ln.cantidad_lineas, 0) AS cantidad_lineas
        FROM CuentasPorPagarDB.dbo.CP04_cuentas_por_pagar c
        JOIN dim.factura f ON f.id_cuenta_pagar = c.id_cuenta_pagar
        LEFT JOIN movimientos mv ON mv.id_cuenta_pagar = c.id_cuenta_pagar
        LEFT JOIN lineas ln ON ln.id_cuenta_pagar = c.id_cuenta_pagar
        LEFT JOIN dim.estado es ON es.id_estado = c.id_estado
        OUTER APPLY (
            SELECT TOP (1) p.proveedor_key
            FROM dim.proveedor p
            WHERE p.id_proveedor = c.id_proveedor
              AND CAST(c.fecha_emision AS DATE) BETWEEN CAST(p.fecha_inicio_validez AS DATE)
                  AND ISNULL(CAST(p.fecha_fin_validez AS DATE), '9999-12-31')
            ORDER BY p.version DESC
        ) pv
    )
    MERGE fact.facturas AS d
    USING origen AS s
       ON d.factura_key = s.factura_key
    WHEN MATCHED THEN
        UPDATE SET id_cuenta_pagar = s.id_cuenta_pagar,
                   tiempo_emision_key = s.tiempo_emision_key,
                   tiempo_vencimiento_key = s.tiempo_vencimiento_key,
                   proveedor_key = s.proveedor_key,
                   estado_factura_key = s.estado_factura_key,
                   monto_subtotal = s.subtotal,
                   monto_impuesto = s.monto_impuesto,
                   monto_total = s.monto_total,
                   monto_pagado = s.monto_pagado,
                   monto_descuento = s.monto_descuento,
                   monto_nota_credito = s.monto_nota_credito,
                   monto_nota_debito = s.monto_nota_debito,
                   saldo_pendiente = s.saldo_pendiente,
                   dias_vencido = s.dias_vencido,
                   cantidad_lineas = s.cantidad_lineas,
                   fecha_ultima_carga = SYSDATETIME()
    WHEN NOT MATCHED THEN
        INSERT (factura_key, id_cuenta_pagar, tiempo_emision_key, tiempo_vencimiento_key,
                proveedor_key, estado_factura_key, monto_subtotal, monto_impuesto, monto_total,
                monto_pagado, monto_descuento, monto_nota_credito, monto_nota_debito,
                saldo_pendiente, dias_vencido, cantidad_lineas, fecha_ultima_carga)
        VALUES (s.factura_key, s.id_cuenta_pagar, s.tiempo_emision_key, s.tiempo_vencimiento_key,
                s.proveedor_key, s.estado_factura_key, s.subtotal, s.monto_impuesto, s.monto_total,
                s.monto_pagado, s.monto_descuento, s.monto_nota_credito, s.monto_nota_debito,
                s.saldo_pendiente, s.dias_vencido, s.cantidad_lineas, SYSDATETIME());
END;
GO

/* -------------------------------------------------------------------------------------
   Hecho pagos (transaccional por movimiento).
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_fact_pagos
AS
BEGIN
    SET NOCOUNT ON;

    ;WITH origen AS (
        SELECT m.id_movimiento,
               f.factura_key,
               CONVERT(INT, CONVERT(CHAR(8), m.fecha_movimiento, 112)) AS tiempo_pago_key,
               CASE WHEN m.fecha_aprobacion IS NULL THEN 0
                    ELSE CONVERT(INT, CONVERT(CHAR(8), CAST(m.fecha_aprobacion AS DATE), 112)) END AS tiempo_aprobacion_key,
               ISNULL(pv.proveedor_key, 0) AS proveedor_key,
               ISNULL(de.estado_key, 0) AS estado_movimiento_key,
               ISNULL(dtm.tipo_movimiento_key, 0) AS tipo_movimiento_key,
               ISNULL(dtp.tipo_pago_key, 0) AS tipo_pago_key,
               m.monto AS monto_movimiento,
               CASE WHEN tm.nombre = 'Pago' THEN m.monto ELSE 0 END AS monto_pago,
               CASE WHEN tm.nombre = 'Descuento por pronto pago' THEN m.monto ELSE 0 END AS monto_descuento,
               CASE WHEN tm.nombre = 'Nota de crédito' THEN m.monto ELSE 0 END AS monto_nota_credito,
               CASE WHEN tm.nombre = 'Nota de débito' THEN m.monto ELSE 0 END AS monto_nota_debito,
               DATEDIFF(DAY, c.fecha_emision, m.fecha_movimiento) AS dias_desde_emision,
               DATEDIFF(DAY, m.fecha_movimiento, c.fecha_vencimiento) AS dias_anticipacion,
               m.conciliado,
               m.id_lote,
               m.id_programacion
        FROM CuentasPorPagarDB.dbo.CP07_movimientos m
        JOIN CuentasPorPagarDB.dbo.CP04_cuentas_por_pagar c ON c.id_cuenta_pagar = m.id_cuenta_pagar
        JOIN CuentasPorPagarDB.dbo.CP02_tipos_movimiento tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
        JOIN dim.factura f ON f.id_cuenta_pagar = m.id_cuenta_pagar
        LEFT JOIN dim.estado de ON de.id_estado = m.id_estado
        LEFT JOIN dim.tipo_movimiento dtm ON dtm.id_tipo_movimiento = m.id_tipo_movimiento
        LEFT JOIN dim.tipo_pago dtp ON dtp.id_tipo_pago = m.id_tipo_pago
        OUTER APPLY (
            SELECT TOP (1) p.proveedor_key
            FROM dim.proveedor p
            WHERE p.id_proveedor = c.id_proveedor
              AND CAST(m.fecha_movimiento AS DATE) BETWEEN CAST(p.fecha_inicio_validez AS DATE)
                  AND ISNULL(CAST(p.fecha_fin_validez AS DATE), '9999-12-31')
            ORDER BY p.version DESC
        ) pv
    )
    MERGE fact.pagos AS d
    USING origen AS s
       ON d.id_movimiento = s.id_movimiento
    WHEN MATCHED THEN
        UPDATE SET factura_key = s.factura_key,
                   tiempo_pago_key = s.tiempo_pago_key,
                   tiempo_aprobacion_key = s.tiempo_aprobacion_key,
                   proveedor_key = s.proveedor_key,
                   estado_movimiento_key = s.estado_movimiento_key,
                   tipo_movimiento_key = s.tipo_movimiento_key,
                   tipo_pago_key = s.tipo_pago_key,
                   monto_movimiento = s.monto_movimiento,
                   monto_pago = s.monto_pago,
                   monto_descuento = s.monto_descuento,
                   monto_nota_credito = s.monto_nota_credito,
                   monto_nota_debito = s.monto_nota_debito,
                   dias_desde_emision = s.dias_desde_emision,
                   dias_anticipacion = s.dias_anticipacion,
                   conciliado = s.conciliado,
                   id_lote = s.id_lote,
                   id_programacion = s.id_programacion,
                   fecha_ultima_carga = SYSDATETIME()
    WHEN NOT MATCHED THEN
        INSERT (id_movimiento, factura_key, tiempo_pago_key, tiempo_aprobacion_key, proveedor_key,
                estado_movimiento_key, tipo_movimiento_key, tipo_pago_key, monto_movimiento,
                monto_pago, monto_descuento, monto_nota_credito, monto_nota_debito,
                dias_desde_emision, dias_anticipacion, conciliado, id_lote, id_programacion,
                fecha_ultima_carga)
        VALUES (s.id_movimiento, s.factura_key, s.tiempo_pago_key, s.tiempo_aprobacion_key, s.proveedor_key,
                s.estado_movimiento_key, s.tipo_movimiento_key, s.tipo_pago_key, s.monto_movimiento,
                s.monto_pago, s.monto_descuento, s.monto_nota_credito, s.monto_nota_debito,
                s.dias_desde_emision, s.dias_anticipacion, s.conciliado, s.id_lote, s.id_programacion,
                SYSDATETIME());
END;
GO

/* -------------------------------------------------------------------------------------
   Snapshot diario de antigüedad.
   Reemplaza el snapshot del día para que sea idempotente.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_cargar_aging_snapshot
    @fecha_corte DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    DECLARE @tiempo_key INT = CONVERT(INT, CONVERT(CHAR(8), @fecha_corte, 112));

    IF NOT EXISTS (SELECT 1 FROM dim.tiempo WHERE tiempo_key = @tiempo_key)
        THROW 51001, 'La fecha de corte no existe en dim.tiempo.', 1;

    DELETE FROM fact.aging_snapshot WHERE tiempo_key = @tiempo_key;

    INSERT INTO fact.aging_snapshot (tiempo_key, factura_key, proveedor_key, estado_factura_key,
        saldo_pendiente, dias_vencido, bucket_antiguedad, monto_total, monto_pagado, fecha_ultima_carga)
    SELECT @tiempo_key,
           f.factura_key,
           f.proveedor_key,
           f.estado_factura_key,
           f.saldo_pendiente,
           f.dias_vencido,
           CASE WHEN f.dias_vencido <= 30 THEN '0-30'
                WHEN f.dias_vencido <= 60 THEN '31-60'
                WHEN f.dias_vencido <= 90 THEN '61-90'
                ELSE '90+' END,
           f.monto_total,
           f.monto_pagado,
           SYSDATETIME()
    FROM fact.facturas f
    JOIN dim.estado e ON e.estado_key = f.estado_factura_key
    WHERE f.saldo_pendiente > 0
      AND e.codigo <> 'ANULADA';
END;
GO

/* -------------------------------------------------------------------------------------
   Orquestador.
   ------------------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE etl.sp_ejecutar_todo
    @fecha_corte DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET @fecha_corte = ISNULL(@fecha_corte, CAST(SYSDATETIME() AS DATE));

    IF NOT EXISTS (SELECT 1 FROM dim.tiempo WHERE fecha = @fecha_corte)
        THROW 51000, 'Debe poblar dim.tiempo antes de ejecutar el ETL.', 1;

    DECLARE @id_ejecucion BIGINT;
    INSERT INTO etl.ejecucion (proceso, estado) VALUES ('ETL_COMPLETO', 'EN_PROCESO');
    SET @id_ejecucion = SCOPE_IDENTITY();

    BEGIN TRY
        EXEC etl.sp_cargar_dim_estado;
        EXEC etl.sp_cargar_dim_tipo_movimiento;
        EXEC etl.sp_cargar_dim_tipo_pago;
        EXEC etl.sp_cargar_dim_proveedor;
        EXEC etl.sp_cargar_dim_factura;
        EXEC etl.sp_cargar_fact_facturas @fecha_corte = @fecha_corte;
        EXEC etl.sp_cargar_fact_pagos;
        EXEC etl.sp_cargar_aging_snapshot @fecha_corte = @fecha_corte;

        UPDATE etl.ejecucion
        SET fecha_fin = SYSDATETIME(), estado = 'OK', mensaje_error = NULL
        WHERE id_ejecucion = @id_ejecucion;

        SELECT @id_ejecucion AS id_ejecucion, 'OK' AS estado;
    END TRY
    BEGIN CATCH
        INSERT INTO etl.error (id_ejecucion, proceso, mensaje)
        VALUES (@id_ejecucion, 'ETL_COMPLETO', ERROR_MESSAGE());

        UPDATE etl.ejecucion
        SET fecha_fin = SYSDATETIME(), estado = 'ERROR', mensaje_error = ERROR_MESSAGE()
        WHERE id_ejecucion = @id_ejecucion;

        THROW;
    END CATCH;
END;
GO
