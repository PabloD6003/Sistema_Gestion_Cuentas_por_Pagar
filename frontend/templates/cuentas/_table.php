<?php
$visibleTotal = (int) ($total ?? 0);
$recordLabel = $visibleTotal === 1 ? 'registro' : 'registros';
?>
<?php if (!empty($oob)): ?>
    <span class="tag is-light" id="cuentas-total" hx-swap-oob="true"><?= e((string) $visibleTotal) ?> <?= $recordLabel ?></span>
<?php endif; ?>
<div class="table-container">
    <table class="table is-fullwidth is-hoverable is-narrow">
        <thead>
            <tr>
                <th>Factura</th>
                <th>Proveedor</th>
                <th>Emisión</th>
                <th>Vencimiento</th>
                <th class="has-text-right">Total</th>
                <th class="has-text-right">Saldo</th>
                <th>Estado</th>
                <th>Días</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="9">
                        <div class="app-empty-state py-6">
                            <p class="has-text-weight-semibold">No se encontraron facturas.</p>
                            <p class="has-text-grey is-size-7">Ajuste los filtros o registre una nueva factura.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $dias = isset($item['dias_vencido']) ? (int) $item['dias_vencido'] : null;
                    $estado = strtoupper((string) ($item['estado'] ?? ''));
                    $rowClass = $dias !== null && $dias > 0 && (float) ($item['saldo_pendiente'] ?? 0) > 0 && $estado !== 'ANULADA' ? 'is-overdue-row' : '';
                    ?>
                    <tr class="<?= e($rowClass) ?>">
                        <td><strong><?= e($item['numero_factura'] ?? '—') ?></strong></td>
                        <td><?= e($item['razon_social'] ?? '—') ?></td>
                        <td><?= fmt_date($item['fecha_emision'] ?? null) ?></td>
                        <td><?= fmt_date($item['fecha_vencimiento'] ?? null) ?></td>
                        <td class="has-text-right"><?= money($item['monto_total'] ?? 0) ?></td>
                        <td class="has-text-right"><strong><?= money($item['saldo_pendiente'] ?? 0) ?></strong></td>
                        <td><?= state_badge($estado) ?></td>
                        <td>
                            <?php if ($estado === 'PAGADA' || $estado === 'ANULADA'): ?>
                                <span class="has-text-grey">—</span>
                            <?php elseif ($dias !== null && $dias > 0): ?>
                                <span class="has-text-danger has-text-weight-semibold"><?= e((string) $dias) ?> días</span>
                            <?php else: ?>
                                <span class="has-text-grey"><?= e(fmt_days($dias)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="has-text-right">
                            <a class="button is-small is-light" href="/cuentas/<?= (int) ($item['id_cuenta_pagar'] ?? 0) ?>">Detalle</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
