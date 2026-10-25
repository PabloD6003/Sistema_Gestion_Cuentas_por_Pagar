# Operación del ETL

## Ejecución manual

```sql
USE CuentasPorPagarDW;
EXEC etl.sp_ejecutar_todo @fecha_corte = '2026-10-24';
```

Sin `@fecha_corte`, toma la fecha actual. El procedimiento:

1. Valida que la fecha exista en `dim.tiempo`.
2. Registra la ejecución en `etl.ejecucion`.
3. Carga dimensiones y hechos.
4. Reemplaza el snapshot de antigüedad del día.
5. Marca la ejecución como `OK` o `ERROR`.

## Validación

```sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql/dw/05_validacion.sql
```

Todos los controles deben devolver `OK`.

## Programación diaria

SQL Server Express no incluye SQL Agent. Opciones:

### Task Scheduler de Windows

Crear una tarea diaria a las 02:00 que ejecute:

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -Q "EXEC CuentasPorPagarDW.etl.sp_ejecutar_todo"
```

### SQL Agent (edición Standard o superior)

```sql
USE msdb;
EXEC dbo.sp_add_job @job_name = N'CxP_ETL_Diario';
EXEC dbo.sp_add_jobstep
    @job_name = N'CxP_ETL_Diario',
    @step_name = N'Ejecutar ETL',
    @subsystem = N'TSQL',
    @command = N'EXEC CuentasPorPagarDW.etl.sp_ejecutar_todo;',
    @database_name = N'CuentasPorPagarDW';
EXEC dbo.sp_add_schedule
    @schedule_name = N'CxP_ETL_0200',
    @freq_type = 4,
    @freq_interval = 1,
    @active_start_time = 020000;
EXEC dbo.sp_attach_schedule @job_name = N'CxP_ETL_Diario', @schedule_name = N'CxP_ETL_0200';
EXEC dbo.sp_add_jobserver @job_name = N'CxP_ETL_Diario';
```

## Reejecución

El ETL es idempotente:

- Dimensiones: `MERGE` por clave natural.
- Proveedor: solo crea una versión si cambia el hash.
- Hechos: `MERGE` por clave natural.
- Aging: elimina y vuelve a insertar el snapshot del día.

## Orden de instalación

1. `sql/oltp/00_base.sql`
2. `sql/oltp/01_correcciones.sql`
3. `sql/oltp/02_lectura.sql`
4. `sql/dw/01_schema_dw.sql`
5. `sql/dw/02_dim_tiempo.sql`
6. `sql/dw/03_etl.sql`
7. `sql/dw/04_views.sql`
8. `sql/dw/05_validacion.sql`
