<?php
$total = 0.0;
foreach ($items as $row) {
    $total += (float) ($row['saldo_pendiente'] ?? 0);
}
?>
<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Snapshot actual</p>
                <h3 class="title is-5 mb-0">Antigüedad de saldos</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-light">Total <?= money($total) ?></span>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay saldos abiertos en el Data Warehouse.</p>
            <p class="has-text-grey is-size-7">Ejecute el ETL para generar el snapshot diario.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-narrow">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th>Factura</th>
                        <th>Emisión</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th class="has-text-right">Saldo</th>
                        <th class="has-text-right">Días vencido</th>
                        <th>Bucket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><?= e($row['razon_social'] ?? '—') ?></td>
                            <td><?= e($row['numero_factura'] ?? '—') ?></td>
                            <td><?= fmt_date($row['fecha_emision'] ?? null) ?></td>
                            <td><?= fmt_date($row['fecha_vencimiento'] ?? null) ?></td>
                            <td><?= state_badge($row['estado'] ?? null) ?></td>
                            <td class="has-text-right"><strong><?= money($row['saldo_pendiente'] ?? 0) ?></strong></td>
                            <td class="has-text-right"><?= e((string) ($row['dias_vencido'] ?? 0)) ?></td>
                            <td><span class="tag is-light"><?= e($row['bucket_antiguedad'] ?? '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
