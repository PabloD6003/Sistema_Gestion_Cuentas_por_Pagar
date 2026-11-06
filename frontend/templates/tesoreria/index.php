<?php
$siguientes = [
    'GENERADO' => [['ENVIADO', 'Marcar enviado', 'is-link'], ['RECHAZADO', 'Rechazar', 'is-danger']],
    'ENVIADO' => [['PROCESADO', 'Marcar procesado', 'is-success'], ['RECHAZADO', 'Rechazar', 'is-danger']],
];
$bancoActual = null;
foreach ($bancos as $banco) {
    if ((int) $banco['id_banco'] === (int) $idBanco) {
        $bancoActual = $banco;
    }
}
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Tesorería</p>
            <h2 class="title is-4 mb-1">Lotes y conciliación bancaria</h2>
            <p class="has-text-grey">Agrupe los pagos aprobados en lotes por banco y concílielos con el extracto bancario.</p>
        </div>
    </div>
</div>

<div>
        <div class="box app-panel">
            <div class="level mb-4">
                <div class="level-left">
                    <h3 class="title is-5 mb-0">Lotes de transferencia</h3>
                </div>
                <div class="level-right">
                    <form method="post" action="/tesoreria/lotes" class="field has-addons mb-0">
                        <?= csrf_field() ?>
                        <div class="control">
                            <div class="select is-small">
                                <select name="id_banco" required>
                                    <?php foreach ($bancos as $banco): ?>
                                        <option value="<?= (int) $banco['id_banco'] ?>" <?= (int) $banco['id_banco'] === (int) $idBanco ? 'selected' : '' ?>><?= e($banco['nombre_banco']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="control">
                            <button class="button is-small is-primary" type="submit">Generar lote</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-hoverable is-narrow">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Banco</th>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="has-text-right">Pagos</th>
                            <th scope="col" class="has-text-right">Monto</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lotes)): ?>
                            <tr><td colspan="7"><div class="app-empty-state py-5"><p class="has-text-grey">No hay lotes generados.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($lotes as $lote): ?>
                                <?php $estadoLote = strtoupper((string) ($lote['estado'] ?? '')); ?>
                                <tr>
                                    <td><?= (int) ($lote['id_lote'] ?? 0) ?></td>
                                    <td><?= e($lote['nombre_banco'] ?? '—') ?></td>
                                    <td><?= fmt_datetime($lote['fecha_envio'] ?? null) ?></td>
                                    <td class="has-text-right"><?= e((string) ($lote['cantidad_pagos'] ?? 0)) ?></td>
                                    <td class="has-text-right"><?= money($lote['monto_total'] ?? 0) ?></td>
                                    <td><?= state_badge($estadoLote, (string) ($lote['estado_nombre'] ?? $estadoLote)) ?></td>
                                    <td>
                                        <?php if (isset($siguientes[$estadoLote])): ?>
                                            <div class="buttons are-small">
                                                <?php foreach ($siguientes[$estadoLote] as [$estado, $etiqueta, $color]): ?>
                                                    <form method="post" action="/tesoreria/lotes/<?= (int) $lote['id_lote'] ?>/estado"
                                                          <?= $estado === 'RECHAZADO' ? 'onsubmit="return confirm(\'¿Rechazar el lote? Sus pagos vuelven a quedar disponibles.\');"' : '' ?>>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="estado" value="<?= e($estado) ?>">
                                                        <button class="button is-small is-light <?= e($color) ?>" type="submit"><?= e($etiqueta) ?></button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="is-size-7 has-text-grey">Sin acciones</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($conciliacion)): ?>
            <div class="box app-panel mt-5">
                <div class="level mb-4">
                    <div class="level-left">
                        <h3 class="title is-5 mb-0">Resultado de la conciliación</h3>
                    </div>
                    <div class="level-right">
                        <span class="tag is-link is-light"><?= e((string) $conciliacion['conciliados']) ?> de <?= e((string) count($conciliacion['items'])) ?> conciliadas</span>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table is-fullwidth is-narrow">
                        <thead>
                            <tr><th scope="col">Línea</th><th scope="col">Fecha</th><th scope="col">Referencia</th><th scope="col" class="has-text-right">Monto</th><th scope="col">Pago</th><th scope="col">Resultado</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conciliacion['items'] as $linea): ?>
                                <tr>
                                    <td><?= e((string) ($linea['linea'] ?? '')) ?></td>
                                    <td><?= fmt_date($linea['fecha'] ?? null) ?></td>
                                    <td class="app-nowrap"><?= e($linea['referencia'] ?? '') ?></td>
                                    <td class="has-text-right"><?= money($linea['monto'] ?? 0) ?></td>
                                    <td><?= $linea['id_movimiento'] === null ? '—' : '#' . (int) $linea['id_movimiento'] ?></td>
                                    <td>
                                        <?php if ($linea['id_movimiento'] !== null): ?>
                                            <span class="tag is-success is-light">Conciliado</span>
                                        <?php else: ?>
                                            <span class="tag is-warning is-light">Sin coincidencia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
</div>

<div class="columns is-variable is-5 mt-2">
    <div class="column is-7">
        <div class="box app-panel">
            <h3 class="title is-5 mb-3">Conciliar extracto bancario</h3>
            <form method="get" action="/tesoreria" class="mb-4">
                <div class="field">
                    <label class="label is-small" for="banco">Banco</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="banco" name="banco" onchange="this.form.submit()">
                                <?php foreach ($bancos as $banco): ?>
                                    <option value="<?= (int) $banco['id_banco'] ?>" <?= (int) $banco['id_banco'] === (int) $idBanco ? 'selected' : '' ?>>
                                        <?= e($banco['nombre_banco']) ?> · <?= e($banco['numero_cuenta']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <form method="post" action="/tesoreria/conciliaciones">
                <?= csrf_field() ?>
                <input type="hidden" name="id_banco" value="<?= (int) $idBanco ?>">
                <div class="field">
                    <label class="label is-small" for="extracto">Movimientos del extracto *</label>
                    <div class="control">
                        <textarea class="textarea is-family-monospace is-size-7" id="extracto" name="extracto" rows="7" required
                                  placeholder="2026-10-31;TRF-1004;1220400.00&#10;2026-10-21;TRF-5005;508500.00"></textarea>
                    </div>
                    <p class="help">Una línea por movimiento: fecha, referencia y monto, separados por punto y coma, coma o tabulador (se puede pegar desde Excel).</p>
                </div>
                <div class="field">
                    <label class="label is-small" for="tolerancia_dias">Tolerancia de fecha (días)</label>
                    <div class="control">
                        <input class="input" id="tolerancia_dias" name="tolerancia_dias" type="number" min="0" max="30" value="3">
                    </div>
                </div>
                <button class="button is-primary is-fullwidth" type="submit" <?= $bancoActual === null ? 'disabled' : '' ?>>Conciliar</button>
            </form>
        </div>
    </div>

    <div class="column is-5">
        <div class="box app-panel">
            <div class="level mb-3">
                <div class="level-left">
                    <h3 class="title is-6 mb-0">Pagos aprobados sin conciliar</h3>
                </div>
                <div class="level-right">
                    <span class="tag is-light"><?= e((string) $totalPendientes) ?></span>
                </div>
            </div>
            <?php if (empty($pendientes)): ?>
                <p class="has-text-grey is-size-7">No hay pagos pendientes de conciliar para este banco.</p>
            <?php else: ?>
                <?php foreach ($pendientes as $pago): ?>
                    <div class="app-kv">
                        <span><?= fmt_date($pago['fecha_movimiento'] ?? null) ?> · <?= e($pago['referencia'] ?? '—') ?></span>
                        <strong><?= money($pago['monto'] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
