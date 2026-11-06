<?php
$tipos = [
    'COMPLETO' => ['Respaldo completo', 'Copia toda la base. Es el punto de partida de los demás.', 'is-primary'],
    'DIFERENCIAL' => ['Respaldo diferencial', 'Solo los cambios desde el último respaldo completo.', 'is-link'],
    'LOG' => ['Respaldo de log', 'Incremental: transacciones desde el último respaldo de log.', 'is-info'],
];
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Mantenimiento</p>
            <h2 class="title is-4 mb-1">Respaldos</h2>
            <p class="has-text-grey">Respaldo de CuentasPorPagarDB en el servidor de SQL Server, verificado al terminar.</p>
        </div>
    </div>
</div>

<div class="columns is-multiline mb-4">
    <?php foreach ($tipos as $tipo => [$titulo, $descripcion, $color]): ?>
        <div class="column is-12-mobile is-4-tablet">
            <div class="box app-panel" style="height: 100%;">
                <h3 class="title is-6 mb-2"><?= e($titulo) ?></h3>
                <p class="has-text-grey is-size-7 mb-4"><?= e($descripcion) ?></p>
                <form method="post" action="/respaldos" onsubmit="this.querySelector('button').classList.add('is-loading');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                    <button class="button <?= e($color) ?> is-fullwidth" type="submit">Generar <?= e(strtolower($titulo)) ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <h3 class="title is-5 mb-0">Historial</h3>
        </div>
        <div class="level-right">
            <span class="tag is-light">Últimos <?= e((string) count($items)) ?></span>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <?= \App\View::render('partials/alert', ['type' => 'warning', 'message' => 'No se pudo cargar el historial: ' . $error], null) ?>
    <?php endif; ?>

    <div class="table-container">
        <table class="table is-fullwidth is-hoverable is-narrow">
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="has-text-right">Tamaño</th>
                    <th scope="col">Generado por</th>
                    <th scope="col">Archivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="app-empty-state py-6">
                                <p class="has-text-weight-semibold">Todavía no hay respaldos.</p>
                                <p class="has-text-grey is-size-7">Genere primero un respaldo completo.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= fmt_datetime($item['fecha_fin'] ?? null) ?></td>
                            <td><span class="tag is-light"><?= e($item['tipo'] ?? '—') ?></span></td>
                            <td class="has-text-right"><?= e(number_format((float) ($item['tamano_mb'] ?? 0), 2)) ?> MB</td>
                            <td><?= e($item['generado_por'] ?? 'Fuera del sistema') ?></td>
                            <td class="is-size-7" style="word-break: break-all;"><?= e($item['archivo'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
