# Data Warehouse — Cuentas por Pagar

## Alcance

- OLTP: `CuentasPorPagarDB`, moneda única CRC.
- DW: `CuentasPorPagarDW`, misma instancia de SQL Server.
- Carga: batch diario a las 02:00.
- El DW es histórico/analítico; la operación diaria se consulta en el OLTP.

## Matriz de bus

| Proceso | Tiempo | Proveedor | Estado | Tipo movimiento | Tipo pago | Factura |
|---|---|---|---|---|---|---|
| Facturas | emisión, vencimiento | X | X |  |  | X |
| Pagos | pago, aprobación | X | X | X | X | X |
| Antigüedad | corte diario | X | X |  |  | X |

## Granularidad

- `fact.facturas`: una fila por cuenta por pagar. Es un hecho acumulativo: se actualiza cuando cambia el saldo o el estado.
- `fact.pagos`: una fila por movimiento. No se agrega; se actualiza su estado.
- `fact.aging_snapshot`: una fila por factura abierta y fecha de corte.

## Dimensiones

- `dim.tiempo`: calendario 2020–2035.
- `dim.proveedor`: SCD Tipo 2 para días de crédito, banco y estado.
- `dim.estado`: estados de factura, movimiento, lote, notificación y sincronización.
- `dim.tipo_movimiento`: pago, descuento, nota de crédito y nota de débito.
- `dim.tipo_pago`: método, banco y requisito de banco.
- `dim.factura`: atributos descriptivos de la factura para relacionar pagos.

Todas las dimensiones tienen el miembro desconocido con clave `0`.

## Medidas

### Facturas

- `monto_subtotal`
- `monto_impuesto`
- `monto_total`
- `monto_pagado`
- `monto_descuento`
- `saldo_pendiente`
- `dias_vencido`
- `cantidad_lineas`

### Pagos

- `monto_movimiento`
- `monto_pago`
- `monto_descuento`
- `monto_nota_credito`
- `monto_nota_debito`
- `dias_desde_emision`
- `dias_anticipacion`

### Antigüedad

- `saldo_pendiente`
- `dias_vencido`
- `bucket_antiguedad`

## ETL

1. Cargar `dim.tiempo`.
2. Cargar `dim.proveedor` con SCD2.
3. Cargar `dim.estado`, `dim.tipo_movimiento`, `dim.tipo_pago` y `dim.factura`.
4. Hacer `MERGE` en `fact.facturas`.
5. Hacer `MERGE` en `fact.pagos`.
6. Insertar el snapshot del día en `fact.aging_snapshot`.
7. Registrar la ejecución en `etl.ejecucion`.

## Reglas de calidad

- Toda fila de hecho debe tener dimensiones válidas.
- Si no se encuentra una dimensión, se usa la clave `0` (desconocido).
- `saldo_pendiente` no puede ser negativo.
- La suma de `fact.facturas.monto_total` debe coincidir con las facturas no anuladas del OLTP.
- La suma de `fact.pagos.monto_movimiento` debe coincidir con los movimientos aprobados del OLTP.
- El ETL debe ser idempotente: ejecutarlo dos veces no duplica filas.

## Reportes

- Antigüedad actual e histórica.
- Pagos por mes, proveedor y banco.
- Cumplimiento de plazos de pago.
- Descuentos obtenidos.
- Evolución de cuentas por pagar.
- Top proveedores por saldo.
