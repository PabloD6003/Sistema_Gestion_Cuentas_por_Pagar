# Correcciones aplicadas al OLTP

Archivo: `sql/oltp/01_correcciones.sql`

| Hallazgo | Corrección | Prueba |
|---|---|---|
| `UQ_CP03_nombre` impedía repetir el nombre de un tipo de pago en bancos distintos. | Se reemplazó por índices únicos filtrados `(id_banco, nombre)` y `(nombre)` cuando no hay banco. | Se insertaron dos tipos "Transferencia" en bancos distintos. |
| El descuento por pronto pago quedaba vivo al anular el pago. | Se agregó `CP07.id_movimiento_origen` y el procedimiento `sp_pago_anular`, que anula pago y descuento en una sola transacción. | Se anuló un pago con descuento: ambos movimientos quedaron `ANULADO` y el saldo volvió al valor original. |
| `sp_movimiento_anular` no bloqueaba pagos con descuento. | Ahora lanza el error 50076 y obliga a usar `sp_pago_anular`. | Prueba negativa ejecutada correctamente. |
| Se podía anular un movimiento dentro de un lote y dejar el lote descuadrado. | Se bloquea cualquier movimiento con `id_lote` no nulo. Se permite pasar un lote de `GENERADO` a `RECHAZADO` para liberar los movimientos. | Se rechazó un lote y después se anuló el pago. |
| `sp_lote_generar` incluía pagos ya conciliados. | Se agregó `m.conciliado = 0` al filtro. | Un pago conciliado no generó lote; el siguiente pago no conciliado sí. |
| Se podía registrar un pago sin método de pago. | `sp_movimiento_registrar` exige `id_tipo_pago` cuando el tipo de movimiento es `Pago`. | Validación agregada. |
| Se podía registrar un movimiento antes de la fecha de emisión. | Se valida `fecha_movimiento >= fecha_emision`. | Validación agregada. |

## Capa de lectura

Archivo: `sql/oltp/02_lectura.sql`

- Vistas para proveedores, facturas, detalle, movimientos, bancos, tipos de pago, usuarios, roles, programaciones, lotes, notificaciones, catálogos y resumen del dashboard.
- Los roles de la aplicación reciben `SELECT` solo sobre las vistas, no sobre las tablas base.

## Pendientes del OLTP

Estos puntos no se corrigieron en esta entrega y deben atenderse antes de producción:

1. `sp_programaciones_ejecutar` no tiene bloqueo seguro para ejecuciones concurrentes. Dos procesos pueden ejecutar la misma programación.
2. La programación parcial no conserva el remanente cuando el monto disponible es menor al programado.
3. Falta `sp_cuenta_pagar_actualizar` para editar una factura sin pagos aprobados.
4. `sp_usuario_crear` no tiene `GRANT` para ningún rol; falta desbloqueo y reseteo de contraseña.
5. El contexto de sesión puede quedar fijado después de un login fallido; revisar `sp_contexto_usuario` y el flujo de autenticación.
6. El flujo de caja duplica facturas y programaciones; revisar las consultas de reporte.
7. La auditoría no cubre `CP05`, `CP11`, `CP15` ni `CP19`.
8. `CP01.identificacion_tributaria` es única global; si se admite el mismo documento en países distintos, cambiar a una clave compuesta por país.
9. Faltan procedimientos de listado paginado para la UI; las vistas cubren el primer listado, pero no la paginación.
10. El script base original tenía el prefijo `SI ` al inicio; en la copia del repositorio se eliminó para que `sqlcmd` pudiera ejecutarlo.
