<?php /* Encabezado que solo aparece al imprimir o guardar como PDF */ ?>
<div class="print-only print-header">
    <p class="print-header-title">Sistema de Gestión de Cuentas por Pagar</p>
    <p class="print-header-meta">Generado el <?= fmt_datetime(date('Y-m-d H:i:s')) ?></p>
</div>

<div class="level mb-5 reportes-header">
    <div class="level-left">
        <div>
            <p class="app-section-eyebrow">Business Intelligence</p>
            <h2 class="title is-4 mb-1">Reportes</h2>
            <p class="has-text-grey">Indicadores calculados desde el Data Warehouse y el snapshot de antigüedad.</p>
        </div>
    </div>
    <div class="level-right">
        <span class="tag is-light">ETL batch diario</span>
        <button class="button is-small is-light no-print" type="button" id="imprimir-reporte">
            Imprimir o guardar PDF
        </button>
    </div>
</div>

<div class="reporte-tabs-wrap">
    <div class="tabs is-boxed reporte-tabs">
        <ul>
            <li<?= ($tipoActivo ?? 'aging') === 'aging' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=aging" class="reporte-tab" data-tipo="aging" hx-get="/partials/reportes/aging" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Antigüedad</a></li>
            <li<?= ($tipoActivo ?? 'aging') === 'pagos' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=pagos" class="reporte-tab" data-tipo="pagos" hx-get="/partials/reportes/pagos" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Pagos por mes</a></li>
            <li<?= ($tipoActivo ?? 'aging') === 'cumplimiento' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=cumplimiento" class="reporte-tab" data-tipo="cumplimiento" hx-get="/partials/reportes/cumplimiento" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Cumplimiento</a></li>
            <li<?= ($tipoActivo ?? 'aging') === 'saldos' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=saldos" class="reporte-tab" data-tipo="saldos" hx-get="/partials/reportes/saldos" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Saldos por proveedor</a></li>
            <li<?= ($tipoActivo ?? 'aging') === 'flujo' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=flujo" class="reporte-tab" data-tipo="flujo" hx-get="/partials/reportes/flujo" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Flujo de caja</a></li>
            <li<?= ($tipoActivo ?? 'aging') === 'etl' ? ' class="is-active"' : '' ?>><a href="/reportes?tipo=etl" class="reporte-tab" data-tipo="etl" hx-get="/partials/reportes/etl" hx-target="#reporte-contenido" hx-swap="innerHTML" hx-indicator="#reporte-loading">Estado ETL</a></li>
        </ul>
    </div>
    <span id="reporte-loading" class="htmx-indicator has-text-grey is-size-7">Cargando reporte…</span>
</div>

<div id="reporte-contenido">
    <?= $contenido ?>
</div>
