<?php
$id = (int) ($proveedor['id_proveedor'] ?? 0);
$resumen = $resumen ?? [];
$promedio = $resumen['calificacion_promedio'] ?? null;
$estrellas = static fn (int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Expediente del proveedor</p>
            <h2 class="title is-4 mb-1"><?= e($proveedor['razon_social'] ?? '—') ?></h2>
            <p class="has-text-grey"><?= e($proveedor['identificacion_tributaria'] ?? '') ?> · <?= e($proveedor['nombre_comercial'] ?? '') ?></p>
        </div>
    </div>
    <div class="level-right">
        <div class="buttons">
            <a class="button is-light" href="/proveedores">Volver</a>
            <a class="button is-light" href="/cuentas?<?= e(http_build_query(['q' => (string) ($proveedor['razon_social'] ?? '')])) ?>">Ver facturas</a>
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
            <p class="app-stat-label">Calificación promedio</p>
            <p class="app-stat-value"><?= $promedio === null ? '—' : e(number_format((float) $promedio, 2)) ?></p>
            <span class="tag is-light"><?= e((string) count($evaluaciones)) ?> evaluaciones</span>
        </div>
    </div>
</div>

<div class="columns is-variable is-5">
    <div class="column is-7">
        <div class="box app-panel">
            <h3 class="title is-5 mb-4">Historial de pagos</h3>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable is-narrow">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Factura</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th class="has-text-right">Monto</th>
                            <th>Conciliación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="6"><div class="app-empty-state py-5"><p class="has-text-grey">Sin pagos aprobados.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= fmt_date($mov['fecha_movimiento'] ?? null) ?></td>
                                    <td><?= e($mov['numero_factura'] ?? '—') ?></td>
                                    <td><?= e($mov['tipo_movimiento'] ?? '—') ?></td>
                                    <td><?= e($mov['referencia'] ?? '—') ?></td>
                                    <td class="has-text-right"><?= money($mov['monto'] ?? 0) ?></td>
                                    <td><?= !empty($mov['conciliado']) ? '<span class="tag is-link">Conciliado</span>' : '<span class="tag is-light">Pendiente</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="column is-5">
        <div class="box app-panel">
            <h3 class="title is-5 mb-4">Evaluar relación comercial</h3>
            <form method="post" action="/proveedores/<?= $id ?>/evaluaciones">
                <?= csrf_field() ?>
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="calificacion">Calificación *</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="calificacion" name="calificacion" required>
                                        <?php for ($n = 5; $n >= 1; $n--): ?>
                                            <option value="<?= $n ?>"><?= $n ?> · <?= e($estrellas($n)) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="fecha_evaluacion">Fecha</label>
                            <div class="control">
                                <input class="input" id="fecha_evaluacion" name="fecha_evaluacion" type="date"
                                       value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label is-small" for="comentario">Comentario</label>
                    <div class="control">
                        <textarea class="textarea" id="comentario" name="comentario" rows="2" maxlength="250"
                                  placeholder="Puntualidad, calidad, atención..."></textarea>
                    </div>
                </div>
                <button class="button is-primary is-fullwidth" type="submit">Registrar evaluación</button>
            </form>
        </div>

        <div class="box app-panel mt-5">
            <h3 class="title is-5 mb-3">Evaluaciones</h3>
            <?php if (empty($evaluaciones)): ?>
                <p class="has-text-grey is-size-7">Todavía no hay evaluaciones.</p>
            <?php else: ?>
                <?php foreach ($evaluaciones as $ev): ?>
                    <div class="app-kv">
                        <span><?= fmt_date($ev['fecha_evaluacion'] ?? null) ?> · <?= e($estrellas((int) ($ev['calificacion'] ?? 0))) ?></span>
                        <strong><?= e($ev['comentario'] ?? '—') ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="box app-panel mt-5">
            <h3 class="title is-5 mb-3">Datos del expediente</h3>
            <div class="app-kv"><span>Contacto</span><strong><?= e($proveedor['contacto_nombre'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Correo</span><strong><?= e($proveedor['correo'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Teléfono</span><strong><?= e($proveedor['telefono'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Dirección</span><strong><?= e($proveedor['direccion'] ?? '—') ?></strong></div>
            <div class="app-kv"><span>Banco</span><strong><?= e($proveedor['banco'] ?? '—') ?> · <?= e($proveedor['cuenta_bancaria'] ?? '') ?></strong></div>
            <div class="app-kv"><span>Código ERP</span><strong><?= e($proveedor['codigo_erp'] ?? '—') ?></strong></div>
        </div>
    </div>
</div>
