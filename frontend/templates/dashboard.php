<?php
$summary = $summary ?? [];
$recent = $recent ?? [];
$buckets = $buckets ?? [];
$bucketTotal = (float) ($bucketTotal ?? 0);
$etl = $etl ?? null;
$warning = $warning ?? null;

$currentUser = \App\Auth::user() ?? [];
$fullName = trim((string) ($currentUser['nombre_completo'] ?? 'usuario'));
$firstName = explode(' ', $fullName !== '' ? $fullName : 'usuario')[0];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

$bucketLabels = [
    '0-30' => '0 a 30 días',
    '31-60' => '31 a 60 días',
    '61-90' => '61 a 90 días',
    '90+' => 'Más de 90 días',
];
$bucketColors = [
    '0-30' => 'is-success',
    '31-60' => 'is-info',
    '61-90' => 'is-warning',
    '90+' => 'is-danger',
];
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Vista general</p>
            <h2 class="title is-4 mb-1"><?= e($greeting) ?>, <?= e($firstName) ?></h2>
            <p class="has-text-grey">Resumen de cuentas por pagar, vencimientos y actividad reciente.</p>
        </div>
    </div>
    <div class="level-right">
        <a class="button is-primary" href="/cuentas/nueva">Nueva factura</a>
    </div>
</div>

<?php if ($warning): ?>
    <div class="notification is-warning is-light"><?= e($warning) ?></div>
<?php endif; ?>

<div class="columns is-multiline app-stats">
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Cuentas abiertas</p>
            <p class="app-stat-value"><?= e(\App\Support\Format::number($summary['cuentas_abiertas'] ?? 0)) ?></p>
            <span class="tag is-warning is-light">Por pagar</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Saldo pendiente</p>
            <p class="app-stat-value app-stat-money"><?= money($summary['saldo_pendiente'] ?? 0) ?></p>
            <span class="tag is-info is-light">Cartera total</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Cuentas vencidas</p>
            <p class="app-stat-value"><?= e(\App\Support\Format::number($summary['cuentas_vencidas'] ?? 0)) ?></p>
            <span class="tag is-danger is-light">Requieren atención</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Pagos del mes</p>
            <p class="app-stat-value app-stat-money"><?= money($summary['pagos_mes'] ?? 0) ?></p>
            <span class="tag is-success is-light">Aprobados</span>
        </div>
    </div>
</div>

<div class="columns is-variable is-6 mt-1">
    <div class="column is-7">
        <div class="box app-panel">
            <div class="level mb-4">
                <div class="level-left">
                    <div>
                        <p class="app-section-eyebrow">Cartera</p>
                        <h3 class="title is-5 mb-0">Antigüedad de saldos</h3>
                    </div>
                </div>
                <div class="level-right">
                    <span class="tag is-light">Snapshot DW</span>
                </div>
            </div>

            <?php if ($bucketTotal <= 0): ?>
                <div class="app-empty-state">
                    <p class="has-text-weight-semibold">Sin saldos abiertos en el Data Warehouse.</p>
                    <p class="has-text-grey is-size-7">Ejecute el ETL diario para actualizar el snapshot de antigüedad.</p>
                </div>
            <?php else: ?>
                <div class="aging-list">
                    <?php foreach ($bucketLabels as $key => $label): ?>
                        <?php
                        $value = (float) ($buckets[$key] ?? 0);
                        $pct = $bucketTotal > 0 ? ($value / $bucketTotal) * 100 : 0;
                        ?>
                        <div class="aging-row">
                            <div class="aging-row-head">
                                <span><?= e($label) ?></span>
                                <strong><?= money($value) ?></strong>
                            </div>
                            <progress class="progress <?= e($bucketColors[$key] ?? 'is-primary') ?> is-small" value="<?= e(number_format($pct, 2, '.', '')) ?>" max="100"><?= e(number_format($pct, 1)) ?>%</progress>
                            <span class="aging-pct"><?= e(number_format($pct, 1)) ?>% del saldo</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="aging-total">
                    <span>Total en aging</span>
                    <strong><?= money($bucketTotal) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="column is-5">
        <div class="box app-panel">
            <div class="level mb-4">
                <div class="level-left">
                    <div>
                        <p class="app-section-eyebrow">Data Warehouse</p>
                        <h3 class="title is-5 mb-0">Última carga ETL</h3>
                    </div>
                </div>
            </div>

            <?php if (is_array($etl)): ?>
                <div class="etl-status">
                    <div>
                        <p class="has-text-grey is-size-7">Estado</p>
                        <?= state_badge($etl['estado'] ?? null, $etl['estado'] ?? '—') ?>
                    </div>
                    <div>
                        <p class="has-text-grey is-size-7">Proceso</p>
                        <p class="has-text-weight-semibold"><?= e($etl['proceso'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="has-text-grey is-size-7">Inicio</p>
                        <p><?= fmt_datetime($etl['fecha_inicio'] ?? null) ?></p>
                    </div>
                    <div>
                        <p class="has-text-grey is-size-7">Fin</p>
                        <p><?= fmt_datetime($etl['fecha_fin'] ?? null) ?></p>
                    </div>
                </div>
                <?php if (!empty($etl['mensaje_error'])): ?>
                    <div class="notification is-danger is-light mt-4"><?= e($etl['mensaje_error']) ?></div>
                <?php endif; ?>
                <a class="button is-small is-light mt-4" href="/reportes">Ver reportes</a>
            <?php else: ?>
                <div class="app-empty-state">
                    <p class="has-text-weight-semibold">Sin ejecuciones registradas.</p>
                    <p class="has-text-grey is-size-7">Ejecute el ETL del DW para habilitar los reportes históricos.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="box app-panel mt-5">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Operación</p>
                <h3 class="title is-5 mb-0">Facturas pendientes recientes</h3>
            </div>
        </div>
        <div class="level-right">
            <a class="button is-small is-light" href="/cuentas">Ver todas</a>
        </div>
    </div>

    <div class="table-container">
        <table class="table is-fullwidth is-hoverable is-narrow app-recent-table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Proveedor</th>
                    <th>Vence</th>
                    <th class="has-text-right">Total</th>
                    <th class="has-text-right">Saldo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="app-empty-state">
                                <p class="has-text-weight-semibold">No hay facturas pendientes.</p>
                                <p class="has-text-grey is-size-7">Registre una nueva factura para comenzar.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><a class="has-text-weight-semibold" href="/cuentas/<?= (int) ($item['id_cuenta_pagar'] ?? 0) ?>"><?= e($item['numero_factura'] ?? '—') ?></a></td>
                            <td><?= e($item['razon_social'] ?? '—') ?></td>
                            <td><?= fmt_date($item['fecha_vencimiento'] ?? null) ?></td>
                            <td class="has-text-right"><?= money($item['monto_total'] ?? 0) ?></td>
                            <td class="has-text-right"><strong><?= money($item['saldo_pendiente'] ?? 0) ?></strong></td>
                            <td><?= state_badge($item['estado'] ?? null) ?></td>
                            <td class="has-text-right"><a class="button is-small is-light" href="/cuentas/<?= (int) ($item['id_cuenta_pagar'] ?? 0) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
