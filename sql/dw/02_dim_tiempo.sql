/* =====================================================================================
   Pobla dim.tiempo entre 2020-01-01 y 2035-12-31.
   Es idempotente: si ya existen fechas del rango, no vuelve a insertar.
   ===================================================================================== */
USE CuentasPorPagarDW;
GO
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
GO

IF NOT EXISTS (SELECT 1 FROM dim.tiempo WHERE fecha >= '2020-01-01')
BEGIN
    ;WITH fechas AS (
        SELECT CAST('2020-01-01' AS DATE) AS fecha
        UNION ALL
        SELECT DATEADD(DAY, 1, fecha)
        FROM fechas
        WHERE fecha < '2035-12-31'
    ),
    calendario AS (
        SELECT fecha,
               CONVERT(INT, CONVERT(CHAR(8), fecha, 112)) AS tiempo_key,
               YEAR(fecha) AS anio,
               DATEPART(QUARTER, fecha) AS trimestre,
               MONTH(fecha) AS mes,
               CONVERT(CHAR(7), fecha, 120) AS anio_mes,
               DATEPART(ISO_WEEK, fecha) AS semana,
               DAY(fecha) AS dia,
               ((DATEPART(WEEKDAY, fecha) + @@DATEFIRST - 2) % 7) + 1 AS dia_semana
        FROM fechas
    )
    INSERT INTO dim.tiempo (tiempo_key, fecha, anio, trimestre, mes, nombre_mes, anio_mes,
                            semana, dia, dia_semana, nombre_dia, es_fin_semana, es_feriado)
    SELECT tiempo_key,
           fecha,
           anio,
           trimestre,
           mes,
           CHOOSE(mes, 'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'),
           anio_mes,
           semana,
           dia,
           dia_semana,
           CHOOSE(dia_semana, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'),
           CASE WHEN dia_semana IN (6,7) THEN 1 ELSE 0 END,
           0
    FROM calendario
    OPTION (MAXRECURSION 0);
END;
GO
