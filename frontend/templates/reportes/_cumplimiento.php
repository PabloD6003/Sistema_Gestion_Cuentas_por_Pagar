<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Indicador de gestión</p>
                <h3 class="title is-5 mb-0">Cumplimiento del plazo de pago</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-light">Pagos aprobados</span>
        </div>
    </div>

    <form id="cumplimiento-filtros" class="filters mb-4" hx-get="/partials/reportes/cumplimiento" hx-target="#reporte-contenido"
          hx-swap="innerHTML" hx-indicator="#reporte-loading" hx-trigger="submit">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label is-small" for="cumplimiento-desde">Desde el mes</label>
                <input class="input is-small" id="cumplimiento-desde" type="month" name="desde" value="<?= e($filtros['desde'] ?? '') ?>">
            </div>
            <div class="control">
                <label class="label is-small" for="cumplimiento-hasta">Hasta el mes</label>
                <input class="input is-small" id="cumplimiento-hasta" type="month" name="hasta" value="<?= e($filtros['hasta'] ?? '') ?>">
            </div>
            <div class="control" style="align-self: flex-end;">
                <button class="button is-small is-primary" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <?php if (empty($items)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay pagos suficientes para calcular el indicador.</p>
            <p class="has-text-grey is-size-7">El cálculo se actualiza con el ETL diario.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-narrow">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th class="has-text-right">Pagos</th>
                        <th class="has-text-right">En plazo</th>
                        <th class="has-text-right">Tarde</th>
                        <th>Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php $pct = (float) ($row['pct_en_plazo'] ?? 0); ?>
                        <tr>
                            <td><strong><?= e(fmt_month($row['anio_mes'] ?? null)) ?></strong></td>
                            <td class="has-text-right"><?= e((string) ($row['cantidad_pagos'] ?? 0)) ?></td>
                            <td class="has-text-right has-text-success has-text-weight-semibold"><?= e((string) ($row['pagos_en_plazo'] ?? 0)) ?></td>
                            <td class="has-text-right has-text-danger has-text-weight-semibold"><?= e((string) ($row['pagos_tarde'] ?? 0)) ?></td>
                            <td>
                                <div class="app-progress-cell">
                                    <progress class="progress <?= $pct >= 80 ? 'is-success' : ($pct >= 50 ? 'is-warning' : 'is-danger') ?> is-small" value="<?= e(number_format($pct, 2, '.', '')) ?>" max="100"><?= e(number_format($pct, 1)) ?>%</progress>
                                    <span><?= e(number_format($pct, 2)) ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
