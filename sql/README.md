# Scripts de base de datos

Este directorio contiene la implementación de base de datos del sistema.

## Orden de ejecución

Ejecute los scripts con `sqlcmd` (o desde SSMS) en el orden indicado. Todos los scripts asumen una instancia de SQL Server local de desarrollo.

### OLTP

1. `oltp/00_base.sql`
   - Crea `CuentasPorPagarDB`.
   - Crea tablas, índices, catálogos, funciones, triggers, procedimientos y roles.
   - Es el script base recibido; no se modifica directamente.
   - **Es destructivo**: vuelve a crear los objetos de la base. No lo ejecute sobre datos que necesite conservar.
2. `oltp/01_correcciones.sql`
   - Corrige los hallazgos críticos del script base.
   - Agrega el vínculo pago-descuento.
   - Agrega procedimientos de anulación de pago y consulta.
3. `oltp/02_lectura.sql`
   - Crea vistas de lectura para la aplicación PHP.
   - Otorga `SELECT` únicamente sobre las vistas, no sobre las tablas base.
4. `oltp/03_usuarios_demo.sql`
   - Crea los usuarios de prueba `admin`, `tesorero` y `contador`.
   - Es idempotente: si un usuario ya existe, lo omite.
   - No usar estas credenciales en producción.

### Data Warehouse

1. `dw/01_schema_dw.sql`
   - Crea `CuentasPorPagarDW`.
   - Crea esquemas `dim`, `fact`, `etl` y `rpt`.
2. `dw/02_dim_tiempo.sql`
   - Puebla `dim.tiempo` entre 2020 y 2035.
3. `dw/03_etl.sql`
   - Crea los procedimientos del ETL. **No carga datos por sí solo.**
4. Ejecute la carga inicial:
   ```sql
   USE CuentasPorPagarDW;
   EXEC etl.sp_ejecutar_todo;
   ```
5. `dw/04_views.sql`
   - Crea las vistas de reportes analíticos.
6. `dw/05_validacion.sql`
   - Ejecuta controles de calidad e idempotencia del DW.
   - Confirme visualmente que todos los controles devuelvan `OK`.

La carga puede repetirse; el ETL está diseñado para ser idempotente.

## Comandos de ejemplo

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\00_base.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\01_correcciones.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\02_lectura.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\03_usuarios_demo.sql

sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\01_schema_dw.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\02_dim_tiempo.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\03_etl.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -Q "USE CuentasPorPagarDW; EXEC etl.sp_ejecutar_todo;"
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\04_views.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\05_validacion.sql
```

Si usa autenticación SQL, reemplace `-E` por `-U <usuario> -P <contraseña>`.

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
