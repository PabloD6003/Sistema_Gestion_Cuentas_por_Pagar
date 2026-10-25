<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Cartera</p>
            <h2 class="title is-4 mb-1">Cuentas por pagar</h2>
            <p class="has-text-grey">Facturas registradas, saldos pendientes y vencimientos.</p>
        </div>
    </div>
    <div class="level-right">
        <a class="button is-primary" href="/cuentas/nueva">Nueva factura</a>
    </div>
</div>

<div class="box app-panel">
    <div class="level mb-4">
        <div class="level-left">
            <h3 class="title is-5 mb-0">Facturas</h3>
        </div>
        <div class="level-right">
            <span class="tag is-light" id="cuentas-total"><?= e((string) ($total ?? 0)) ?> <?= ((int) ($total ?? 0)) === 1 ? 'registro' : 'registros' ?></span>
        </div>
    </div>

    <form id="cuentas-filtros" class="filters" method="get" action="/cuentas">
        <div class="field is-grouped is-grouped-multiline">
            <div class="control is-expanded">
                <input class="input" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>"
                       placeholder="Buscar por factura o proveedor"
                       hx-get="/partials/cuentas" hx-trigger="input changed delay:400ms, search"
                       hx-target="#cuentas-tabla" hx-include="#cuentas-filtros">
            </div>
            <div class="control">
                <div class="select">
                    <select name="estado"
                            hx-get="/partials/cuentas" hx-trigger="change"
                            hx-target="#cuentas-tabla" hx-include="#cuentas-filtros">
                        <option value="">Todos los estados</option>
                        <?php foreach (['PENDIENTE', 'PARCIAL', 'PAGADA', 'ANULADA'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="control">
                <label class="checkbox app-check">
                    <input type="checkbox" name="vencidas" value="1"
                           <?= ($filters['vencidas'] ?? '') === '1' ? 'checked' : '' ?>
                           hx-get="/partials/cuentas" hx-trigger="change"
                           hx-target="#cuentas-tabla" hx-include="#cuentas-filtros">
                    Solo vencidas
                </label>
            </div>
            <div class="control">
                <button class="button is-light" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <div id="cuentas-tabla">
        <?php require APP_BASE_PATH . '/templates/cuentas/_table.php'; ?>
    </div>
</div>
