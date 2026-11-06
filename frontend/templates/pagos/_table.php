<?php
$visibleTotal = (int) ($total ?? 0);
$recordLabel = $visibleTotal === 1 ? 'registro' : 'registros';
?>
<?php if (!empty($oob)): ?>
    <span class="tag is-light" id="pagos-total" role="status" aria-live="polite" hx-swap-oob="true"><?= e((string) $visibleTotal) ?> <?= $recordLabel ?></span>
<?php endif; ?>
<div class="table-container">
    <table class="table is-fullwidth is-hoverable is-narrow app-pagos-table">
        <thead>
            <tr>
                <th scope="col">Factura</th>
                <th scope="col">Proveedor</th>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col" class="has-text-right">Monto</th>
                <th scope="col">Estado / conciliación</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7">
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
                    <tr title="Movimiento #<?= $movId ?>">
                        <td>
                            <a href="/cuentas/<?= $cuentaId ?>" class="has-text-weight-semibold app-nowrap"><?= e($item['numero_factura'] ?? '—') ?></a>
                            <?php if (!empty($item['referencia'])): ?>
                                <p class="is-size-7 has-text-grey app-nowrap"><?= e($item['referencia']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="app-col-name"><?= e($item['razon_social'] ?? '—') ?></td>
                        <td class="app-nowrap"><?= fmt_date($item['fecha_movimiento'] ?? null) ?></td>
                        <td>
                            <?php if ($isDiscount): ?>
                                <span>Descuento</span>
                                <p class="is-size-7 has-text-grey app-nowrap">pronto pago</p>
                            <?php else: ?>
                                <?= e($movTipo ?: '—') ?>
                            <?php endif; ?>
                        </td>
                        <td class="has-text-right"><strong><?= money($item['monto'] ?? 0) ?></strong></td>
                        <td>
                            <?= state_badge($movEstado) ?>
                            <p class="is-size-7 mt-1 <?= !empty($item['conciliado']) ? 'has-text-link' : 'has-text-grey' ?> app-nowrap">
                                <?= !empty($item['conciliado']) ? 'Conciliado' : 'Sin conciliar' ?>
                            </p>
                        </td>
                        <td>
                            <?php if ($isDiscount && $movEstado === 'REGISTRADO'): ?>
                                <span class="is-size-7 has-text-grey">Se aprueba con el pago</span>
                            <?php elseif ($canApproveRow && $movEstado === 'REGISTRADO'): ?>
                                <form method="post" action="/pagos/<?= $movId ?>/aprobar">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="/pagos">
                                    <button class="button is-small is-success is-light" type="submit">Aprobar</button>
                                </form>
                            <?php elseif ($canApproveRow && $movEstado === 'APROBADO' && !$isDiscount && empty($item['conciliado'])): ?>
                                <details class="app-inline-action">
                                    <summary class="button is-small is-danger is-light">Anular</summary>
                                    <form method="post" action="/pagos/<?= $movId ?>/anular" class="app-inline-form" onsubmit="return confirm('¿Anular este pago? Se restaurará el saldo de la factura.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="/pagos">
                                        <input class="input is-small" name="motivo" placeholder="Motivo de la anulación" maxlength="150" required>
                                        <button class="button is-small is-danger" type="submit">Confirmar</button>
                                    </form>
                                </details>
                            <?php elseif ($canApproveRow && $movEstado === 'APROBADO' && !empty($item['conciliado'])): ?>
                                <span class="is-size-7 has-text-grey">No anulable</span>
                            <?php elseif ($isDiscount && $movEstado === 'APROBADO'): ?>
                                <span class="is-size-7 has-text-grey">Se anula con el pago</span>
                            <?php else: ?>
                                <span class="is-size-7 has-text-grey" aria-label="Sin acciones disponibles">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
