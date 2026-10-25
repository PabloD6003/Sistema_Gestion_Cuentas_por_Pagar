<div class="level mb-5">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Business Intelligence</p>
            <h2 class="title is-4 mb-1">Reportes</h2>
            <p class="has-text-grey">Indicadores calculados desde el Data Warehouse y el snapshot de antigüedad.</p>
        </div>
    </div>
    <div class="level-right">
        <span class="tag is-light">ETL batch diario</span>
    </div>
</div>

<div class="reporte-tabs-wrap">
    <div class="tabs is-boxed reporte-tabs">
        <ul>
            <li class="is-active"><a href="/reportes" class="reporte-tab" data-tipo="aging" hx-get="/partials/reportes/aging" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Antigüedad</a></li>
            <li><a href="/reportes" class="reporte-tab" data-tipo="pagos" hx-get="/partials/reportes/pagos" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Pagos por mes</a></li>
            <li><a href="/reportes" class="reporte-tab" data-tipo="cumplimiento" hx-get="/partials/reportes/cumplimiento" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Cumplimiento</a></li>
            <li><a href="/reportes" class="reporte-tab" data-tipo="saldos" hx-get="/partials/reportes/saldos" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Saldos por proveedor</a></li>
            <li><a href="/reportes" class="reporte-tab" data-tipo="etl" hx-get="/partials/reportes/etl" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Estado ETL</a></li>
        </ul>
    </div>
    <span id="reporte-loading" class="htmx-indicator has-text-grey is-size-7">Cargando reporte…</span>
</div>

<div id="reporte-contenido">
    <?= $contenido ?>
</div>
