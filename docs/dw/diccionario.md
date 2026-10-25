# Diccionario del Data Warehouse

Base: `CuentasPorPagarDW`

## Dimensiones

### `dim.tiempo`

Calendario diario de 2020-01-01 a 2035-12-31, más el miembro desconocido `0`.

| Columna | Tipo | Descripción |
|---|---|---|
| `tiempo_key` | INT | Clave `YYYYMMDD`. |
| `fecha` | DATE | Fecha. |
| `anio`, `trimestre`, `mes`, `dia` | SMALLINT/TINYINT | Componentes. |
| `anio_mes` | CHAR(7) | Formato `YYYY-MM`. |
| `semana` | TINYINT | Semana ISO. |
| `dia_semana` | TINYINT | 1 = lunes, 7 = domingo. |
| `es_fin_semana` | BIT | Sábado o domingo. |
| `es_feriado` | BIT | Reservado para feriados; hoy todos en 0. |

### `dim.proveedor` (SCD Tipo 2)

| Columna | Descripción |
|---|---|
| `proveedor_key` | Clave sustituta. |
| `id_proveedor` | Clave natural del OLTP. |
| `identificacion_tributaria`, `razon_social`, `pais` | Atributos del proveedor. |
| `dias_credito`, `banco`, `cuenta_bancaria`, `estado` | Atributos versionados. |
| `fecha_inicio_validez`, `fecha_fin_validez` | Vigencia de la versión. |
| `es_actual` | 1 para la versión vigente. |
| `version` | Número incremental por proveedor. |
| `hash_atributos` | SHA-256 de los atributos versionados. |

La primera versión de un proveedor inicia en `1900-01-01` para que las facturas históricas encuentren una versión válida.

### `dim.estado`

Estados de factura, movimiento, lote, notificación y sincronización. Clave natural `id_estado`.

### `dim.tipo_movimiento`

Tipos de movimiento con su efecto (`AUMENTA` o `DISMINUYE`).

### `dim.tipo_pago`

Tipos de pago con método, banco y bandera `requiere_banco`.

### `dim.factura`

Atributos descriptivos de la factura: `id_cuenta_pagar`, proveedor, número, emisión y vencimiento.

## Hechos

### `fact.facturas`

Grano: una fila por factura (`id_cuenta_pagar`). Se actualiza en cada carga.

Medidas: subtotal, impuesto, total, pagado, descuento, notas de crédito/débito, saldo pendiente, días vencido y cantidad de líneas.

### `fact.pagos`

Grano: una fila por movimiento (`id_movimiento`). Incluye pagos, descuentos y notas.

Medidas: monto del movimiento, monto de pago, descuento, notas, días desde emisión y días de anticipación.

### `fact.aging_snapshot`

Grano: una fila por factura abierta y día de corte (`tiempo_key`, `factura_key`). Se reemplaza para el día al reejecutar el ETL.

Medidas: saldo pendiente, días vencido, bucket (`0-30`, `31-60`, `61-90`, `90+`), total y pagado.

## Control

- `etl.ejecucion`: una fila por ejecución del ETL.
- `etl.error`: errores con el detalle y la ejecución asociada.
