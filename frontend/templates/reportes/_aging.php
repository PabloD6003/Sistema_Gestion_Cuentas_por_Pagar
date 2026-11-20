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

    <form id="aging-filtros" class="filters mb-4" hx-get="/partials/reportes/aging" hx-target="#reporte-contenido"
          hx-swap="innerHTML" hx-indicator="#reporte-loading" hx-trigger="submit">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label is-small" for="aging-buscar">Proveedor o factura</label>
                <input class="input is-small" id="aging-buscar" type="search" name="buscar"
                       value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Buscar">
            </div>
            <div class="control">
                <label class="label is-small" for="aging-bucket">Tramo de antigüedad</label>
                <div class="select is-small">
                    <select id="aging-bucket" name="bucket">
                        <option value="">Todos los tramos</option>
                        <?php foreach (['0-30', '31-60', '61-90', '90+'] as $tramo): ?>
                            <option value="<?= e($tramo) ?>" <?= ($filtros['bucket'] ?? '') === $tramo ? 'selected' : '' ?>>
                                <?= e($tramo) ?> días
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="control" style="align-self: flex-end;">
                <button class="button is-small is-primary" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

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
                            <td class="app-col-name"><?= e($row['razon_social'] ?? '—') ?></td>
                            <td class="app-nowrap"><?= e($row['numero_factura'] ?? '—') ?></td>
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
