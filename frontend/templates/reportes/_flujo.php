<?php
$total = 0.0;
foreach ($items as $row) {
    $total += (float) ($row['total_periodo'] ?? 0);
}
$porMes = ($agrupacion ?? 'SEMANA') === 'MES';
?>
<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Proyección al día · OLTP</p>
                <h3 class="title is-5 mb-0">Flujo de caja proyectado</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-warning is-light">Total a pagar <?= money($total) ?></span>
        </div>
    </div>

    <form id="flujo-filtros" class="filters mb-4" hx-get="/partials/reportes/flujo" hx-target="#reporte-contenido"
          hx-swap="innerHTML" hx-indicator="#reporte-loading" hx-trigger="submit">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label is-small" for="flujo-desde">Desde</label>
                <input class="input is-small" id="flujo-desde" type="date" name="desde" value="<?= e($desde) ?>">
            </div>
            <div class="control">
                <label class="label is-small" for="flujo-hasta">Hasta</label>
                <input class="input is-small" id="flujo-hasta" type="date" name="hasta" value="<?= e($hasta) ?>">
            </div>
            <div class="control">
                <label class="label is-small" for="flujo-agrupacion">Agrupar por</label>
                <div class="select is-small">
                    <select id="flujo-agrupacion" name="agrupacion">
                        <option value="SEMANA" <?= $porMes ? '' : 'selected' ?>>Semana</option>
                        <option value="MES" <?= $porMes ? 'selected' : '' ?>>Mes</option>
                    </select>
                </div>
            </div>
            <div class="control" style="align-self: flex-end;">
                <button class="button is-small is-primary" type="submit">Actualizar</button>
            </div>
        </div>
    </form>

    <?php if (empty($items)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay pagos proyectados en el rango.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-narrow">
                <thead>
                    <tr>
                        <th scope="col"><?= $porMes ? 'Mes' : 'Semana del' ?></th>
                        <th scope="col" class="has-text-right">Cuentas vencidas</th>
                        <th scope="col" class="has-text-right">Cuentas por vencer</th>
                        <th scope="col" class="has-text-right">Pagos programados</th>
                        <th scope="col" class="has-text-right">Total del periodo</th>
                        <th scope="col" class="has-text-right">Acumulado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><strong><?= $porMes ? e(fmt_month(substr((string) ($row['periodo'] ?? ''), 0, 7))) : fmt_date($row['periodo'] ?? null) ?></strong></td>
                            <td class="has-text-right"><?= money($row['cuentas_vencidas'] ?? 0) ?></td>
                            <td class="has-text-right"><?= money($row['cuentas_por_pagar'] ?? 0) ?></td>
                            <td class="has-text-right"><?= money($row['pagos_programados'] ?? 0) ?></td>
                            <td class="has-text-right"><strong><?= money($row['total_periodo'] ?? 0) ?></strong></td>
                            <td class="has-text-right"><?= money($row['total_acumulado'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="help mt-3">Las cuentas ya vencidas se proyectan en el primer periodo. Las cuentas que cubrirán los pagos programados de su proveedor no se cuentan dos veces.</p>
    <?php endif; ?>
</div>
