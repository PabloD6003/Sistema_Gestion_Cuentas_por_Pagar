<?php
$id = (int) ($cuenta['id_cuenta_pagar'] ?? 0);
$estado = strtoupper((string) ($cuenta['estado'] ?? ''));
$saldo = (float) ($cuenta['saldo_pendiente'] ?? 0);
$total = (float) ($cuenta['monto_total'] ?? 0);
$pctDescuento = (float) ($cuenta['pct_descuento_pronto_pago'] ?? 0);
$fechaLimite = $cuenta['fecha_limite_descuento'] ?? null;
$hoy = date('Y-m-d');
$descuentoVigente = $pctDescuento > 0 && !empty($fechaLimite) && $hoy <= (string) $fechaLimite && $saldo > 0;
$montoConDescuento = round($saldo * (1 - ($pctDescuento / 100)), 2);
$montoSugerido = $descuentoVigente ? $montoConDescuento : $saldo;
$canApprove = \App\Auth::canApprove();
$tiposPago = $tiposPago ?? [];
$diasTexto = in_array($estado, ['PAGADA', 'ANULADA'], true)
    ? '—'
    : fmt_days(isset($cuenta['dias_vencido']) ? (int) $cuenta['dias_vencido'] : null);
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Factura</p>
            <h2 class="title is-4 mb-1"><?= e($cuenta['numero_factura'] ?? '—') ?></h2>
            <p class="has-text-grey"><?= e($cuenta['razon_social'] ?? '—') ?></p>
        </div>
    </div>
    <div class="level-right">
        <div class="buttons">
            <a class="button is-light" href="/cuentas">Volver</a>
            <?php if ($canApprove && $estado !== 'ANULADA'): ?>
                <form method="post" action="/cuentas/<?= $id ?>/anular" onsubmit="return confirm('¿Anular esta factura? Esta acción no se puede deshacer.');">
                    <?= csrf_field() ?>
                    <button class="button is-danger is-light" type="submit">Anular factura</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="columns is-multiline app-stats">
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Total factura</p>
            <p class="app-stat-value app-stat-money"><?= money($total) ?></p>
            <?= state_badge($estado) ?>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Saldo pendiente</p>
            <p class="app-stat-value app-stat-money"><?= money($saldo) ?></p>
            <span class="tag is-light"><?= e($diasTexto) ?></span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Vencimiento</p>
            <p class="app-stat-value app-stat-date"><?= fmt_date($cuenta['fecha_vencimiento'] ?? null) ?></p>
            <span class="tag is-light"><?= e((string) ($cuenta['dias_credito'] ?? 0)) ?> días de crédito</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Descuento pronto pago</p>
            <p class="app-stat-value"><?= e(number_format($pctDescuento, 2)) ?>%</p>
            <?php if ($pctDescuento > 0): ?>
                <span class="tag <?= $descuentoVigente ? 'is-success' : 'is-light' ?>">Hasta <?= fmt_date($fechaLimite) ?></span>
            <?php else: ?>
                <span class="tag is-light">No aplica</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="columns is-variable is-6 mt-1">
    <div class="column is-7">
        <div class="box app-panel">
            <h3 class="title is-5 mb-4">Detalle de la factura</h3>
            <div class="table-container">
                <table class="table is-fullwidth is-narrow">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Descripción</th>
                            <th class="has-text-right">Cantidad</th>
                            <th class="has-text-right">Precio</th>
                            <th class="has-text-right">Impuesto</th>
                            <th class="has-text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($cuenta['detalle'] ?? []) as $line): ?>
                            <tr>
                                <td><?= (int) ($line['numero_linea'] ?? 0) ?></td>
                                <td><?= e($line['descripcion'] ?? '') ?></td>
                                <td class="has-text-right"><?= e(number_format((float) ($line['cantidad'] ?? 0), 2)) ?></td>
                                <td class="has-text-right"><?= money($line['precio_unitario'] ?? 0) ?></td>
                                <td class="has-text-right"><?= e(number_format((float) ($line['pct_impuesto'] ?? 0), 2)) ?>%</td>
                                <td class="has-text-right"><strong><?= money($line['total_linea'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="has-text-right">Subtotal</th>
                            <th class="has-text-right"><?= money($cuenta['subtotal'] ?? 0) ?></th>
                        </tr>
                        <tr>
                            <th colspan="5" class="has-text-right">Impuesto</th>
                            <th class="has-text-right"><?= money($cuenta['monto_impuesto'] ?? 0) ?></th>
                        </tr>
                        <tr>
                            <th colspan="5" class="has-text-right">Total</th>
                            <th class="has-text-right"><?= money($total) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="box app-panel mt-5">
            <div class="level mb-4">
                <div class="level-left">
                    <h3 class="title is-5 mb-0">Movimientos</h3>
                </div>
                <div class="level-right">
                    <span class="tag is-light"><?= count($movimientos) ?> registros</span>
                </div>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-hoverable is-narrow">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th class="has-text-right">Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="app-empty-state">
                                        <p class="has-text-weight-semibold">Aún no hay pagos registrados.</p>
                                        <p class="has-text-grey is-size-7">Use el formulario de la derecha para registrar un pago.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <?php
                                $movId = (int) ($mov['id_movimiento'] ?? 0);
                                $movEstado = strtoupper((string) ($mov['estado'] ?? ''));
                                $movTipo = (string) ($mov['tipo_movimiento'] ?? '');
                                $isDiscount = str_contains(strtolower($movTipo), 'descuento');
                                ?>
                                <tr>
                                    <td><?= $movId ?></td>
                                    <td><?= fmt_date($mov['fecha_movimiento'] ?? null) ?></td>
                                    <td><?= e($movTipo) ?></td>
                                    <td>
                                        <span><?= e($mov['referencia'] ?? '—') ?></span>
                                        <?php if (!empty($mov['descripcion'])): ?>
                                            <p class="is-size-7 has-text-grey"><?= e($mov['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="has-text-right"><strong><?= money($mov['monto'] ?? 0) ?></strong></td>
                                    <td><?= state_badge($movEstado) ?></td>
                                    <td>
                                        <?php if ($canApprove && $movEstado === 'REGISTRADO'): ?>
                                            <form method="post" action="/pagos/<?= $movId ?>/aprobar">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="/cuentas/<?= $id ?>">
                                                <button class="button is-small is-success is-light" type="submit">Aprobar</button>
                                            </form>
                                        <?php elseif ($canApprove && $movEstado === 'APROBADO' && !$isDiscount && empty($mov['conciliado'])): ?>
                                            <form method="post" action="/pagos/<?= $movId ?>/anular" class="action-form" onsubmit="return confirm('¿Anular este pago? Se restaurará el saldo de la factura.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="/cuentas/<?= $id ?>">
                                                <input class="input is-small" name="motivo" placeholder="Motivo" required>
                                                <button class="button is-small is-danger is-light" type="submit">Anular</button>
                                            </form>
                                        <?php elseif ($canApprove && $movEstado === 'APROBADO' && !empty($mov['conciliado'])): ?>
                                            <span class="tag is-link">Conciliado</span>
                                        <?php elseif ($isDiscount && $movEstado === 'APROBADO'): ?>
                                            <span class="is-size-7 has-text-grey">Se anula con el pago</span>
                                        <?php elseif ($movEstado === 'ANULADO'): ?>
                                            <span class="is-size-7 has-text-grey">—</span>
                                        <?php else: ?>
                                            <span class="is-size-7 has-text-grey">Sin acciones</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canApprove && $pctDescuento > 0 && $saldo > 0): ?>
                <p class="help mt-3">Si se genera un descuento por pronto pago, se crea un movimiento adicional. Apruebe tanto el pago como el descuento para cerrar la factura.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="column is-5">
        <?php if ($estado === 'ANULADA'): ?>
            <div class="box app-panel">
                <div class="notification is-danger is-light mb-0">Esta factura está anulada. No admite nuevos pagos.</div>
            </div>
        <?php elseif ($saldo <= 0): ?>
            <div class="box app-panel">
                <div class="notification is-success is-light mb-0">La factura está completamente pagada.</div>
            </div>
        <?php else: ?>
            <div class="box app-panel">
                <div class="mb-4">
                    <p class="app-section-eyebrow">Nuevo movimiento</p>
                    <h3 class="title is-5 mb-0">Registrar pago</h3>
                </div>

                <?php if ($descuentoVigente): ?>
                    <div class="notification is-success is-light">
                        Descuento vigente del <strong><?= e(number_format($pctDescuento, 2)) ?>%</strong> hasta el <?= fmt_date($fechaLimite) ?>.
                        Monto con descuento: <strong><?= money($montoConDescuento) ?></strong>.
                    </div>
                <?php endif; ?>

                <?php if (empty($tiposPago)): ?>
                    <div class="notification is-warning is-light">No hay tipos de pago activos. Configure el catálogo antes de registrar pagos.</div>
                <?php else: ?>
                    <form method="post" action="/cuentas/<?= $id ?>/pagos" id="pago-form"
                          data-saldo="<?= e(number_format($saldo, 2, '.', '')) ?>"
                          data-net="<?= e(number_format($montoConDescuento, 2, '.', '')) ?>"
                          data-discount-active="<?= $descuentoVigente ? '1' : '0' ?>">
                        <?= csrf_field() ?>

                        <div class="field">
                            <label class="label is-small" for="monto-pago">Monto *</label>
                            <div class="control">
                                <input class="input" id="monto-pago" name="monto" type="number" min="0.01" max="<?= e(number_format($saldo, 2, '.', '')) ?>" step="0.01"
                                       value="<?= e(number_format($montoSugerido, 2, '.', '')) ?>" required>
                            </div>
                            <p class="help">Saldo pendiente: <?= money($saldo) ?></p>
                        </div>

                        <div class="field">
                            <label class="label is-small" for="id_tipo_pago">Tipo de pago *</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="id_tipo_pago" name="id_tipo_pago" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($tiposPago as $tipo): ?>
                                            <option value="<?= (int) ($tipo['id_tipo_pago'] ?? 0) ?>">
                                                <?= e($tipo['nombre'] ?? '') ?><?= !empty($tipo['nombre_banco']) ? ' · ' . e($tipo['nombre_banco']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label is-small" for="fecha_pago">Fecha de pago</label>
                            <div class="control">
                                <input class="input" id="fecha_pago" name="fecha_pago" type="date" value="<?= e(date('Y-m-d')) ?>">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label is-small" for="referencia">Referencia</label>
                            <div class="control">
                                <input class="input" id="referencia" name="referencia" placeholder="N.º de transferencia o cheque">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label is-small" for="descripcion">Descripción</label>
                            <div class="control">
                                <textarea class="textarea" id="descripcion" name="descripcion" rows="2" placeholder="Observaciones del pago"></textarea>
                            </div>
                        </div>

                        <?php if ($descuentoVigente): ?>
                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox" id="aplicar_descuento" name="aplicar_descuento" value="1" checked>
                                    Aplicar descuento por pronto pago
                                </label>
                            </div>
                        <?php endif; ?>

                        <button class="button is-primary is-fullwidth" type="submit">Registrar pago</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="box app-panel mt-5">
            <h3 class="title is-5 mb-3">Datos de la factura</h3>
            <div class="app-kv"><span>Proveedor</span><strong><?= e($cuenta['razon_social'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Emisión</span><strong><?= fmt_date($cuenta['fecha_emision'] ?? null) ?></strong></div>
            <div class="app-kv"><span>Vencimiento</span><strong><?= fmt_date($cuenta['fecha_vencimiento'] ?? null) ?></strong></div>
            <div class="app-kv"><span>Registro</span><strong><?= fmt_datetime($cuenta['fecha_registro'] ?? null) ?></strong></div>
            <div class="app-kv"><span>Código ERP</span><strong><?= e($cuenta['codigo_erp'] ?? '—') ?></strong></div>
        </div>
    </div>
</div>
