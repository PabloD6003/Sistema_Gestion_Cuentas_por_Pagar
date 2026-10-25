<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Catálogo</p>
            <h2 class="title is-4 mb-1">Proveedores</h2>
            <p class="has-text-grey">Datos maestros, condiciones de crédito y cuentas bancarias.</p>
        </div>
    </div>
    <div class="level-right">
        <a class="button is-primary" href="/cuentas/nueva">Nueva factura</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="notification is-danger is-light"><?= e($error) ?></div>
<?php endif; ?>

<div class="columns is-variable is-6">
    <div class="column is-8">
        <div class="box app-panel">
            <div class="level mb-4">
                <div class="level-left">
                    <h3 class="title is-5 mb-0">Listado</h3>
                </div>
                <div class="level-right">
                    <span class="tag is-light" id="proveedores-total"><?= e((string) ($total ?? 0)) ?> <?= ((int) ($total ?? 0)) === 1 ? 'registro' : 'registros' ?></span>
                </div>
            </div>

            <form id="proveedores-filtros" class="filters" method="get" action="/proveedores">
                <div class="field has-addons">
                    <div class="control is-expanded">
                        <input class="input" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
                               placeholder="Buscar por razón social o identificación"
                               hx-get="/partials/proveedores" hx-trigger="input changed delay:400ms, search"
                               hx-target="#proveedores-tabla" hx-include="#proveedores-filtros">
                    </div>
                    <div class="control">
                        <div class="select">
                            <select name="estado"
                                    hx-get="/partials/proveedores" hx-trigger="change"
                                    hx-target="#proveedores-tabla" hx-include="#proveedores-filtros">
                                <option value="">Todos</option>
                                <option value="1" <?= ($filters['estado'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
                                <option value="0" <?= ($filters['estado'] ?? '') === '0' ? 'selected' : '' ?>>Inactivos</option>
                            </select>
                        </div>
                    </div>
                    <div class="control">
                        <button class="button is-light" type="submit">Filtrar</button>
                    </div>
                </div>
            </form>

            <div id="proveedores-tabla">
                <?php require APP_BASE_PATH . '/templates/proveedores/_table.php'; ?>
            </div>
        </div>
    </div>

    <div class="column is-4">
        <div class="box app-panel">
            <div class="mb-4">
                <p class="app-section-eyebrow">Nuevo registro</p>
                <h3 class="title is-5 mb-0">Agregar proveedor</h3>
            </div>

            <form method="post" action="/proveedores">
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label is-small" for="identificacion_tributaria">Identificación tributaria *</label>
                    <div class="control">
                        <input class="input" id="identificacion_tributaria" name="identificacion_tributaria" required
                               value="<?= e(old($old, 'identificacion_tributaria')) ?>" placeholder="3-101-123456">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="razon_social">Razón social *</label>
                    <div class="control">
                        <input class="input" id="razon_social" name="razon_social" required
                               value="<?= e(old($old, 'razon_social')) ?>" placeholder="Distribuidora Ejemplo S.A.">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="nombre_comercial">Nombre comercial</label>
                    <div class="control">
                        <input class="input" id="nombre_comercial" name="nombre_comercial"
                               value="<?= e(old($old, 'nombre_comercial')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="pais">País *</label>
                    <div class="control">
                        <input class="input" id="pais" name="pais" required value="<?= e(old($old, 'pais', 'Costa Rica')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="direccion">Dirección *</label>
                    <div class="control">
                        <input class="input" id="direccion" name="direccion" required value="<?= e(old($old, 'direccion')) ?>">
                    </div>
                </div>

                <div class="columns is-mobile">
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="telefono">Teléfono *</label>
                            <div class="control">
                                <input class="input" id="telefono" name="telefono" required value="<?= e(old($old, 'telefono')) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label is-small" for="dias_credito">Días crédito *</label>
                            <div class="control">
                                <input class="input" id="dias_credito" name="dias_credito" type="number" min="0" required
                                       value="<?= e(old($old, 'dias_credito', 30)) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="correo">Correo *</label>
                    <div class="control">
                        <input class="input" id="correo" name="correo" type="email" required value="<?= e(old($old, 'correo')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="contacto_nombre">Contacto</label>
                    <div class="control">
                        <input class="input" id="contacto_nombre" name="contacto_nombre" value="<?= e(old($old, 'contacto_nombre')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="banco">Banco *</label>
                    <div class="control">
                        <input class="input" id="banco" name="banco" required value="<?= e(old($old, 'banco')) ?>" placeholder="Banco Nacional">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="cuenta_bancaria">Cuenta bancaria *</label>
                    <div class="control">
                        <input class="input" id="cuenta_bancaria" name="cuenta_bancaria" required value="<?= e(old($old, 'cuenta_bancaria')) ?>" placeholder="CR01...">
                    </div>
                </div>

                <div class="field">
                    <label class="label is-small" for="codigo_erp">Código ERP</label>
                    <div class="control">
                        <input class="input" id="codigo_erp" name="codigo_erp" value="<?= e(old($old, 'codigo_erp')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="estado" value="1" <?= !array_key_exists('estado', $old) || (string) $old['estado'] === '1' ? 'checked' : '' ?>>
                        Proveedor activo
                    </label>
                </div>

                <button class="button is-primary is-fullwidth" type="submit">Registrar proveedor</button>
            </form>
        </div>
    </div>
</div>
