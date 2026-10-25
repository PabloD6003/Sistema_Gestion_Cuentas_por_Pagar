# Scripts de base de datos

Este directorio contiene la implementación de base de datos del sistema.

## Orden de ejecución

### OLTP

1. `oltp/00_base.sql`
   - Crea `CuentasPorPagarDB`.
   - Crea tablas, índices, catálogos, funciones, triggers, procedimientos y roles.
   - Es el script base recibido; no se modifica directamente.
2. `oltp/01_correcciones.sql`
   - Corrige los hallazgos críticos del script base.
   - Agrega el vínculo pago-descuento.
   - Agrega procedimientos de anulación de pago y consulta.
3. `oltp/02_lectura.sql`
   - Crea vistas de lectura para la aplicación PHP.
   - Otorga `SELECT` únicamente sobre las vistas, no sobre las tablas base.

### Data Warehouse

1. `dw/01_schema_dw.sql`
   - Crea `CuentasPorPagarDW`.
   - Crea esquemas `dim`, `fact`, `etl` y `rpt`.
2. `dw/02_dim_tiempo.sql`
   - Puebla `dim.tiempo` entre 2020 y 2035.
3. `dw/03_etl.sql`
   - Carga dimensiones y hechos desde el OLTP.
4. `dw/04_views.sql`
   - Crea las vistas de reportes analíticos.
5. `dw/05_validacion.sql`
   - Ejecuta controles de calidad e idempotencia del DW.

## Requisitos

- SQL Server 2019 o superior.
- La conexión debe usar `SET QUOTED_IDENTIFIER ON` y `SET ANSI_NULLS ON`.
- El ETL se ejecuta en la misma instancia que el OLTP.
- El DW es de solo lectura para la aplicación; solo el usuario/proceso ETL escribe en él.

## Alcance

- Moneda única (CRC).
- Sin tablas de moneda ni tipos de cambio.
- ETL batch diario.
- `dim.proveedor` con SCD Tipo 2.
- Hechos: facturas, pagos y snapshot diario de antigüedad.
