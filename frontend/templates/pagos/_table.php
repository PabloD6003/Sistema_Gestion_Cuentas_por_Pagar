<?php
$visibleTotal = (int) ($total ?? 0);
$recordLabel = $visibleTotal === 1 ? 'registro' : 'registros';
?>
<?php if (!empty($oob)): ?>
    <span class="tag is-light" id="pagos-total" hx-swap-oob="true"><?= e((string) $visibleTotal) ?> <?= $recordLabel ?></span>
<?php endif; ?>
<div class="table-container">
    <table class="table is-fullwidth is-hoverable is-narrow">
        <thead>
            <tr>
                <th>#</th>
                <th>Factura</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th class="has-text-right">Monto</th>
                <th>Estado</th>
                <th>Conciliación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9">
                        <div class="app-empty-state py-6">
                            <p class="has-text-weight-semibold">No se encontraron movimientos.</p>
                            <p class="has-text-grey is-size-7">Ajuste los filtros o registre un pago desde una factura.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $movId = (int) ($item['id_movimiento'] ?? 0);
                    $cuentaId = (int) ($item['id_cuenta_pagar'] ?? 0);
                    $movEstado = strtoupper((string) ($item['estado'] ?? ''));
                    $movTipo = (string) ($item['tipo_movimiento'] ?? '');
                    $isDiscount = str_contains(strtolower($movTipo), 'descuento');
                    $canApproveRow = !empty($canApprove);
                    ?>
                    <tr>
                        <td><?= $movId ?></td>
                        <td>
                            <a href="/cuentas/<?= $cuentaId ?>" class="has-text-weight-semibold"><?= e($item['numero_factura'] ?? '—') ?></a>
                            <?php if (!empty($item['referencia'])): ?>
                                <p class="is-size-7 has-text-grey"><?= e($item['referencia']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['razon_social'] ?? '—') ?></td>
                        <td><?= fmt_date($item['fecha_movimiento'] ?? null) ?></td>
                        <td><?= e($movTipo ?: '—') ?></td>
                        <td class="has-text-right"><strong><?= money($item['monto'] ?? 0) ?></strong></td>
                        <td><?= state_badge($movEstado) ?></td>
                        <td>
                            <?php if (!empty($item['conciliado'])): ?>
                                <span class="tag is-link">Conciliado</span>
                            <?php else: ?>
                                <span class="tag is-light">No conciliado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canApproveRow && $movEstado === 'REGISTRADO'): ?>
                                <form method="post" action="/pagos/<?= $movId ?>/aprobar">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="/pagos">
                                    <button class="button is-small is-success is-light" type="submit">Aprobar</button>
                                </form>
                            <?php elseif ($canApproveRow && $movEstado === 'APROBADO' && !$isDiscount && empty($item['conciliado'])): ?>
                                <form method="post" action="/pagos/<?= $movId ?>/anular" class="action-form" onsubmit="return confirm('¿Anular este pago? Se restaurará el saldo de la factura.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="/pagos">
                                    <input class="input is-small" name="motivo" placeholder="Motivo" required>
                                    <button class="button is-small is-danger is-light" type="submit">Anular</button>
                                </form>
                            <?php elseif ($canApproveRow && $movEstado === 'APROBADO' && !empty($item['conciliado'])): ?>
                                <span class="is-size-7 has-text-grey">No anulable</span>
                            <?php elseif ($isDiscount && $movEstado === 'APROBADO'): ?>
                                <span class="is-size-7 has-text-grey">Se anula con el pago</span>
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
