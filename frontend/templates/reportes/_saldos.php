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
                <p class="app-section-eyebrow">Concentración</p>
                <h3 class="title is-5 mb-0">Saldos por proveedor</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-light">Total <?= money($total) ?></span>
        </div>
    </div>

    <form id="saldos-filtros" class="filters mb-4" hx-get="/partials/reportes/saldos" hx-target="#reporte-contenido"
          hx-swap="innerHTML" hx-indicator="#reporte-loading" hx-trigger="submit">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label is-small" for="saldos-buscar">Proveedor</label>
                <input class="input is-small" id="saldos-buscar" type="search" name="buscar"
                       value="<?= e($filtros['buscar'] ?? '') ?>" placeholder="Razón social">
            </div>
            <div class="control">
                <label class="label is-small" for="saldos-minimo">Saldo mínimo</label>
                <input class="input is-small" id="saldos-minimo" type="number" name="saldo_minimo" min="0" step="1000"
                       value="<?= e($filtros['saldo_minimo'] ?? '') ?>" placeholder="0">
            </div>
            <div class="control" style="align-self: flex-end;">
                <button class="button is-small is-primary" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <?php if (empty($items)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay saldos pendientes por proveedor.</p>
            <p class="has-text-grey is-size-7">El reporte se alimenta del snapshot del Data Warehouse.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-narrow">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th class="has-text-right">Facturas abiertas</th>
                        <th class="has-text-right">Saldo pendiente</th>
                        <th>Participación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $saldo = (float) ($row['saldo_pendiente'] ?? 0);
                        $pct = $total > 0 ? ($saldo / $total) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= e($row['razon_social'] ?? '—') ?></td>
                            <td class="has-text-right"><?= e((string) ($row['facturas_abiertas'] ?? 0)) ?></td>
                            <td class="has-text-right"><strong><?= money($saldo) ?></strong></td>
                            <td>
                                <div class="app-progress-cell">
                                    <progress class="progress is-primary is-small" value="<?= e(number_format($pct, 2, '.', '')) ?>" max="100"><?= e(number_format($pct, 1)) ?>%</progress>
                                    <span><?= e(number_format($pct, 1)) ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
