<?php
$lineas = is_array($old['detalle'] ?? null) ? $old['detalle'] : [];
if ($lineas === []) {
    $lineas = [['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => '', 'pct_impuesto' => 13]];
}
$fechaEmision = old($old, 'fecha_emision', date('Y-m-d'));
?>
<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Registro</p>
            <h2 class="title is-4 mb-1">Nueva factura</h2>
            <p class="has-text-grey">La factura se registra en el OLTP; el descuento por pronto pago es opcional.</p>
        </div>
    </div>
    <div class="level-right">
        <a class="button is-light" href="/cuentas">Volver</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="notification is-danger is-light"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/cuentas" id="factura-form">
    <?= csrf_field() ?>

    <div class="columns is-variable is-6">
        <div class="column is-7">
            <div class="box app-panel">
                <h3 class="title is-5 mb-4">Datos de la factura</h3>

                <div class="field">
                    <label class="label is-small" for="id_proveedor">Proveedor *</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="id_proveedor" name="id_proveedor" required>
                                <option value="">Seleccione un proveedor</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?= (int) ($provider['id_proveedor'] ?? 0) ?>"
                                        <?= (string) old($old, 'id_proveedor') === (string) ($provider['id_proveedor'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($provider['razon_social'] ?? '') ?> · <?= e($provider['identificacion_tributaria'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="numero_factura">Número de factura *</label>
                            <div class="control">
                                <input class="input" id="numero_factura" name="numero_factura" required
                                       value="<?= e(old($old, 'numero_factura')) ?>" placeholder="FAC-2026-001">
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="fecha_emision">Fecha de emisión *</label>
                            <div class="control">
                                <input class="input" id="fecha_emision" name="fecha_emision" type="date" required
                                       value="<?= e($fechaEmision) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="dias_credito">Días de crédito</label>
                            <div class="control">
                                <input class="input" id="dias_credito" name="dias_credito" type="number" min="0"
                                       value="<?= e(old($old, 'dias_credito', 30)) ?>">
                            </div>
                            <p class="help">Si se deja vacío, se usan los días configurados en el proveedor.</p>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="pct_descuento_pronto_pago">Descuento pronto pago (%)</label>
                            <div class="control">
                                <input class="input" id="pct_descuento_pronto_pago" name="pct_descuento_pronto_pago"
                                       type="number" min="0" max="100" step="0.01"
                                       value="<?= e(old($old, 'pct_descuento_pronto_pago', 0)) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="dias_descuento">Días del descuento</label>
                            <div class="control">
                                <input class="input" id="dias_descuento" name="dias_descuento" type="number" min="0"
                                       value="<?= e(old($old, 'dias_descuento')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="codigo_erp">Código ERP</label>
                    <div class="control">
                        <input class="input" id="codigo_erp" name="codigo_erp" value="<?= e(old($old, 'codigo_erp')) ?>">
                    </div>
                </div>
            </div>

            <div class="box app-panel mt-5">
                <div class="level mb-3">
                    <div class="level-left">
                        <h3 class="title is-5 mb-0">Detalle</h3>
                    </div>
                    <div class="level-right">
                        <button class="button is-small is-light" type="button" id="agregar-linea">+ Agregar línea</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table is-fullwidth is-narrow app-lines-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="is-2">Cantidad</th>
                                <th class="is-2">Precio unitario</th>
                                <th class="is-2">Impuesto %</th>
                                <th class="has-text-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="detalle-lineas">
                            <?php foreach ($lineas as $i => $line): ?>
                                <tr class="linea">
                                    <td><input class="input is-small" name="detalle[<?= (int) $i ?>][descripcion]" required value="<?= e($line['descripcion'] ?? '') ?>" placeholder="Servicio o producto"></td>
                                    <td><input class="input is-small cantidad" name="detalle[<?= (int) $i ?>][cantidad]" type="number" min="0.0001" step="0.0001" required value="<?= e($line['cantidad'] ?? 1) ?>"></td>
                                    <td><input class="input is-small precio" name="detalle[<?= (int) $i ?>][precio_unitario]" type="number" min="0" step="0.0001" required value="<?= e($line['precio_unitario'] ?? '') ?>"></td>
                                    <td><input class="input is-small impuesto" name="detalle[<?= (int) $i ?>][pct_impuesto]" type="number" min="0" max="100" step="0.01" value="<?= e($line['pct_impuesto'] ?? 13) ?>"></td>
                                    <td class="has-text-right"><span class="linea-total has-text-weight-semibold">—</span></td>
                                    <td class="has-text-right"><button class="button is-small is-danger is-light remove-linea" type="button">×</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="column is-5">
            <div class="box app-panel app-summary-box">
                <h3 class="title is-5 mb-4">Resumen</h3>
                <div class="app-summary-row"><span>Subtotal</span><strong id="resumen-subtotal">—</strong></div>
                <div class="app-summary-row"><span>Impuesto</span><strong id="resumen-impuesto">—</strong></div>
                <div class="app-summary-row app-summary-total"><span>Total</span><strong id="resumen-total">—</strong></div>

                <div class="notification is-info is-light mt-5">
                    El total se calcula con el impuesto de cada línea. El descuento por pronto pago se aplica al registrar el pago dentro del plazo indicado.
                </div>

                <button class="button is-primary is-fullwidth is-medium mt-4" type="submit">Registrar factura</button>
                <a class="button is-light is-fullwidth mt-2" href="/cuentas">Cancelar</a>
            </div>
        </div>
    </div>
</form>

<template id="linea-template">
    <tr class="linea">
        <td><input class="input is-small" name="detalle[__INDEX__][descripcion]" required placeholder="Servicio o producto"></td>
        <td><input class="input is-small cantidad" name="detalle[__INDEX__][cantidad]" type="number" min="0.0001" step="0.0001" required value="1"></td>
        <td><input class="input is-small precio" name="detalle[__INDEX__][precio_unitario]" type="number" min="0" step="0.0001" required value=""></td>
        <td><input class="input is-small impuesto" name="detalle[__INDEX__][pct_impuesto]" type="number" min="0" max="100" step="0.01" value="13"></td>
        <td class="has-text-right"><span class="linea-total has-text-weight-semibold">—</span></td>
        <td class="has-text-right"><button class="button is-small is-danger is-light remove-linea" type="button">×</button></td>
    </tr>
</template>
