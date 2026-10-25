<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Operación del DW</p>
                <h3 class="title is-5 mb-0">Estado de la última carga ETL</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-light">Batch diario</span>
        </div>
    </div>

    <?php if (!is_array($etl)): ?>
        <div class="app-empty-state">
            <p class="has-text-weight-semibold">No hay ejecuciones del ETL registradas.</p>
            <p class="has-text-grey is-size-7">Ejecute <code>sql/dw/03_etl.sql</code> y vuelva a consultar.</p>
        </div>
    <?php else: ?>
        <div class="columns is-multiline">
            <div class="column is-3">
                <p class="has-text-grey is-size-7">Estado</p>
                <?= state_badge($etl['estado'] ?? null, $etl['estado'] ?? '—') ?>
            </div>
            <div class="column is-3">
                <p class="has-text-grey is-size-7">Proceso</p>
                <p class="has-text-weight-semibold"><?= e($etl['proceso'] ?? '—') ?></p>
            </div>
            <div class="column is-3">
                <p class="has-text-grey is-size-7">Inicio</p>
                <p><?= fmt_datetime($etl['fecha_inicio'] ?? null) ?></p>
            </div>
            <div class="column is-3">
                <p class="has-text-grey is-size-7">Fin</p>
                <p><?= fmt_datetime($etl['fecha_fin'] ?? null) ?></p>
            </div>
        </div>
        <?php if (!empty($etl['mensaje_error'])): ?>
            <div class="notification is-danger is-light mt-4"><?= e($etl['mensaje_error']) ?></div>
        <?php else: ?>
            <div class="notification is-success is-light mt-4">La última ejecución del ETL finalizó sin errores registrados.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
