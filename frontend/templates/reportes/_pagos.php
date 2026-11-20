<?php
$totalPagado = 0.0;
$totalDescuento = 0.0;
foreach ($items as $row) {
    $totalPagado += (float) ($row['monto_pagado'] ?? 0);
    $totalDescuento += (float) ($row['monto_descuento'] ?? 0);
}
?>
<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Flujo de pagos</p>
                <h3 class="title is-5 mb-0">Pagos por mes</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-success is-light">Pagado <?= money($totalPagado) ?></span>
            <span class="tag is-info is-light">Descuentos <?= money($totalDescuento) ?></span>
        </div>
    </div>

    <form id="pagos-filtros" class="filters mb-4" hx-get="/partials/reportes/pagos" hx-target="#reporte-contenido"
          hx-swap="innerHTML" hx-indicator="#reporte-loading" hx-trigger="submit">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label is-small" for="pagos-desde">Desde el mes</label>
                <input class="input is-small" id="pagos-desde" type="month" name="desde" value="<?= e($filtros['desde'] ?? '') ?>">
            </div>
            <div class="control">
                <label class="label is-small" for="pagos-hasta">Hasta el mes</label>
                <input class="input is-small" id="pagos-hasta" type="month" name="hasta" value="<?= e($filtros['hasta'] ?? '') ?>">
            </div>
            <div class="control" style="align-self: flex-end;">
                <button class="button is-small is-primary" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <?php if (empty($items)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay pagos históricos en el Data Warehouse.</p>
            <p class="has-text-grey is-size-7">Ejecute el ETL después de aprobar pagos.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-narrow">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th class="has-text-right">Cantidad de pagos</th>
                        <th class="has-text-right">Monto pagado</th>
                        <th class="has-text-right">Descuentos obtenidos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><strong><?= e(fmt_month($row['anio_mes'] ?? null)) ?></strong></td>
                            <td class="has-text-right"><?= e((string) ($row['cantidad_pagos'] ?? 0)) ?></td>
                            <td class="has-text-right"><?= money($row['monto_pagado'] ?? 0) ?></td>
                            <td class="has-text-right"><?= money($row['monto_descuento'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
