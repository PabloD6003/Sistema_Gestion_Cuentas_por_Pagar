<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Operación</p>
            <h2 class="title is-4 mb-1">Pagos</h2>
            <p class="has-text-grey">Movimientos registrados, aprobaciones y anulaciones.</p>
        </div>
    </div>
    <div class="level-right">
        <a class="button is-primary" href="/cuentas">Ir a cuentas</a>
    </div>
</div>

<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <h3 class="title is-5 mb-0">Movimientos</h3>
        </div>
        <div class="level-right">
            <span class="tag is-light" id="pagos-total"><?= e((string) ($total ?? 0)) ?> <?= ((int) ($total ?? 0)) === 1 ? 'registro' : 'registros' ?></span>
        </div>
    </div>

    <form id="pagos-filtros" class="filters" method="get" action="/pagos">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control is-expanded">
                <input class="input" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
                       placeholder="Buscar por factura, proveedor o referencia"
                       hx-get="/partials/pagos" hx-trigger="input changed delay:400ms, search"
                       hx-target="#pagos-tabla" hx-include="#pagos-filtros">
            </div>
            <div class="control">
                <div class="select">
                    <select name="estado"
                            hx-get="/partials/pagos" hx-trigger="change"
                            hx-target="#pagos-tabla" hx-include="#pagos-filtros">
                        <option value="">Todos los estados</option>
                        <?php foreach (['REGISTRADO', 'APROBADO', 'ANULADO'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="control">
                <div class="select">
                    <select name="tipo"
                            hx-get="/partials/pagos" hx-trigger="change"
                            hx-target="#pagos-tabla" hx-include="#pagos-filtros">
                        <option value="">Todos los tipos</option>
                        <option value="Pago" <?= ($filters['tipo'] ?? '') === 'Pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="Descuento por pronto pago" <?= ($filters['tipo'] ?? '') === 'Descuento por pronto pago' ? 'selected' : '' ?>>Descuento pronto pago</option>
                    </select>
                </div>
            </div>
            <div class="control">
                <div class="select">
                    <select name="conciliado"
                            hx-get="/partials/pagos" hx-trigger="change"
                            hx-target="#pagos-tabla" hx-include="#pagos-filtros">
                        <option value="">Conciliación</option>
                        <option value="1" <?= ($filters['conciliado'] ?? '') === '1' ? 'selected' : '' ?>>Conciliados</option>
                        <option value="0" <?= ($filters['conciliado'] ?? '') === '0' ? 'selected' : '' ?>>No conciliados</option>
                    </select>
                </div>
            </div>
            <div class="control">
                <button class="button is-light" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <div id="pagos-tabla">
        <?php require APP_BASE_PATH . '/templates/pagos/_table.php'; ?>
    </div>
</div>
