<?php
$id = (int) ($proveedor['id_proveedor'] ?? 0);
$resumen = $resumen ?? [];
$promedio = $resumen['calificacion_promedio'] ?? null;
$totalResenas = count($evaluaciones);

// Distribución de las reseñas por calificación (5 a 1)
$distribucion = array_fill(1, 5, 0);
foreach ($evaluaciones as $ev) {
    $n = (int) ($ev['calificacion'] ?? 0);
    if ($n >= 1 && $n <= 5) {
        $distribucion[$n]++;
    }
}

$etiquetas = [5 => 'Excelente', 4 => 'Buena', 3 => 'Regular', 2 => 'Deficiente', 1 => 'Mala'];
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Expediente del proveedor</p>
            <h2 class="title is-4 mb-1"><?= e($proveedor['razon_social'] ?? '—') ?></h2>
            <p class="has-text-grey">
                <?= e($proveedor['identificacion_tributaria'] ?? '') ?>
                <?php if (!empty($proveedor['nombre_comercial'])): ?> · <?= e($proveedor['nombre_comercial']) ?><?php endif; ?>
                · <?= state_badge(!empty($proveedor['estado']) ? 'ACTIVO' : 'INACTIVO', !empty($proveedor['estado']) ? 'Activo' : 'Inactivo') ?>
            </p>
        </div>
    </div>
    <div class="level-right">
        <div class="buttons">
            <a class="button is-light" href="/proveedores">Volver</a>
            <a class="button is-light" href="/cuentas?<?= e(http_build_query(['q' => (string) ($proveedor['razon_social'] ?? '')])) ?>">Ver facturas</a>
            <a class="button is-primary" href="#evaluar">Evaluar proveedor</a>
        </div>
    </div>
</div>

<div class="columns is-multiline mb-4">
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Monto facturado</p>
            <p class="app-stat-value app-stat-money"><?= money($resumen['monto_facturado'] ?? 0) ?></p>
            <span class="tag is-light"><?= e((string) ($resumen['cuentas_registradas'] ?? 0)) ?> facturas</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Saldo pendiente</p>
            <p class="app-stat-value app-stat-money"><?= money($resumen['saldo_pendiente'] ?? 0) ?></p>
            <span class="tag is-light"><?= e((string) ($proveedor['dias_credito'] ?? 0)) ?> días de crédito</span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Total pagado</p>
            <p class="app-stat-value app-stat-money"><?= money($resumen['total_pagado'] ?? 0) ?></p>
            <span class="tag is-success is-light">Descuentos <?= money($resumen['descuentos_obtenidos'] ?? 0) ?></span>
        </div>
    </div>
    <div class="column is-3-desktop is-6-tablet">
        <div class="app-stat-card">
            <p class="app-stat-label">Evaluación comercial</p>
            <p class="app-stat-value"><?= $promedio === null ? '—' : e(number_format((float) $promedio, 2)) ?></p>
            <?= rating_stars($promedio) ?>
        </div>
    </div>
</div>

<div class="box app-panel" id="resenas">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="app-section-eyebrow">Relación comercial</p>
                <h3 class="title is-5 mb-0">Evaluación comercial y reseñas</h3>
            </div>
        </div>
        <div class="level-right">
            <span class="tag is-light"><?= $totalResenas ?> <?= $totalResenas === 1 ? 'reseña' : 'reseñas' ?></span>
        </div>
    </div>

    <div class="columns is-variable is-5">
        <div class="column is-3">
            <div class="app-rating-summary">
                <p class="app-rating-big"><?= $promedio === null ? '—' : e(number_format((float) $promedio, 1)) ?></p>
                <?= rating_stars($promedio) ?>
                <p class="is-size-7 has-text-grey mt-1">Promedio de <?= $totalResenas ?> <?= $totalResenas === 1 ? 'reseña' : 'reseñas' ?></p>
            </div>
            <div class="mt-4">
                <?php for ($n = 5; $n >= 1; $n--): ?>
                    <?php $pct = $totalResenas > 0 ? round($distribucion[$n] * 100 / $totalResenas) : 0; ?>
                    <div class="app-rating-row">
                        <span><?= $n ?> ★</span>
                        <span class="app-rating-bar"><span style="width: <?= (int) $pct ?>%"></span></span>
                        <span class="has-text-right"><?= $distribucion[$n] ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="column is-5">
            <?php if ($evaluaciones === []): ?>
                <div class="app-empty-state py-5">
                    <p class="has-text-weight-semibold">Todavía no hay reseñas.</p>
                    <p class="has-text-grey is-size-7">Registre la primera evaluación con el formulario.</p>
                </div>
            <?php else: ?>
                <div class="app-review-list">
                    <?php foreach ($evaluaciones as $ev): ?>
                        <?php $n = (int) ($ev['calificacion'] ?? 0); ?>
                        <article class="app-review">
                            <div class="app-review-head">
                                <?= rating_stars($n) ?>
                                <span class="tag is-light"><?= e($etiquetas[$n] ?? '') ?></span>
                                <span class="is-size-7 has-text-grey ml-auto"><?= fmt_date($ev['fecha_evaluacion'] ?? null) ?></span>
                            </div>
                            <p><?= e($ev['comentario'] ?? '') ?: '<span class="has-text-grey">Sin comentario</span>' ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="column is-4" id="evaluar">
            <div class="app-form-card">
                <h4 class="title is-6 mb-3">Nueva evaluación</h4>
                <form method="post" action="/proveedores/<?= $id ?>/evaluaciones">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label class="label is-small">Calificación *</label>
                        <div class="app-rating-input">
                            <?php for ($n = 5; $n >= 1; $n--): ?>
                                <input type="radio" id="cal-<?= $n ?>" name="calificacion" value="<?= $n ?>" <?= $n === 5 ? 'required' : '' ?>>
                                <label for="cal-<?= $n ?>" title="<?= e($n . ' · ' . $etiquetas[$n]) ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <p class="help">Puntualidad en entregas, calidad, precios y atención.</p>
                    </div>
                    <div class="field">
                        <label class="label is-small" for="fecha_evaluacion">Fecha</label>
                        <div class="control">
                            <input class="input" id="fecha_evaluacion" name="fecha_evaluacion" type="date"
                                   value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label is-small" for="comentario">Reseña</label>
                        <div class="control">
                            <textarea class="textarea" id="comentario" name="comentario" rows="3" maxlength="250"
                                      placeholder="¿Cómo fue la relación comercial en este periodo?"></textarea>
                        </div>
                    </div>
                    <button class="button is-primary is-fullwidth" type="submit">Registrar evaluación</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="columns is-variable is-5 mt-2">
    <div class="column is-8">
        <div class="box app-panel">
            <h3 class="title is-5 mb-4">Historial de pagos</h3>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable app-table">
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Factura</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Referencia</th>
                            <th scope="col" class="has-text-right">Monto</th>
                            <th scope="col">Conciliación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="6"><div class="app-empty-state py-5"><p class="has-text-grey">Sin pagos aprobados.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td class="app-nowrap"><?= fmt_date($mov['fecha_movimiento'] ?? null) ?></td>
                                    <td class="app-nowrap"><?= e($mov['numero_factura'] ?? '—') ?></td>
                                    <td><?= e($mov['tipo_movimiento'] ?? '—') ?></td>
                                    <td class="app-nowrap"><?= e($mov['referencia'] ?? '—') ?></td>
                                    <td class="has-text-right app-nowrap"><?= money($mov['monto'] ?? 0) ?></td>
                                    <td><?= !empty($mov['conciliado']) ? '<span class="tag is-link is-light">Conciliado</span>' : '<span class="tag is-light">Pendiente</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="column is-4">
        <div class="box app-panel">
            <h3 class="title is-5 mb-3">Datos del expediente</h3>
            <div class="app-kv"><span>Contacto</span><strong><?= e($proveedor['contacto_nombre'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Correo</span><strong><?= e($proveedor['correo'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Teléfono</span><strong><?= e($proveedor['telefono'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Dirección</span><strong><?= e($proveedor['direccion'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Banco</span><strong><?= e($proveedor['banco'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Cuenta</span><strong class="app-nowrap"><?= e($proveedor['cuenta_bancaria'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Código ERP</span><strong><?= e($proveedor['codigo_erp'] ?? '—') ?></strong></div>
        </div>
    </div>
</div>
