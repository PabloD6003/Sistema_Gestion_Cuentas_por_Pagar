<?php
/**
 * Controles de paginación para las tablas que se recargan con HTMX.
 *
 * Variables:
 * - $pagination: ['page' => int, 'pages' => int]
 * - $paginationBase: URL de la página completa (enlace normal y URL del historial), p. ej. '/cuentas'
 * - $paginationEndpoint: URL del parcial HTMX, p. ej. '/partials/cuentas'
 * - $paginationTarget: id del contenedor de la tabla (sin #)
 * - $paginationForm: id del formulario de filtros (sin #)
 * - $filters: filtros actuales, para conservarlos en los enlaces
 */
$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(0, (int) ($pagination['pages'] ?? 0));

if ($pages <= 1) {
    return;
}

$page = min($page, $pages);
$activeFilters = array_filter($filters ?? [], static fn ($value): bool => $value !== '' && $value !== null);

$pageUrl = static function (int $target) use ($paginationBase, $activeFilters): string {
    return $paginationBase . '?' . http_build_query($activeFilters + ['page' => $target]);
};

// Primera, última y dos páginas a cada lado de la actual; el resto se resume con "…"
$visible = [];
for ($n = 1; $n <= $pages; $n++) {
    if ($n === 1 || $n === $pages || abs($n - $page) <= 2) {
        $visible[] = $n;
    }
}

$link = static function (int $target, string $label, string $class = '', string $aria = '') use ($pageUrl, $paginationEndpoint, $paginationTarget, $paginationForm): string {
    return sprintf(
        '<a class="%s" href="%s" hx-get="%s" hx-vals=\'{"page": %d}\' hx-include="#%s" hx-target="#%s" hx-swap="innerHTML show:#%s:top" hx-push-url="%s"%s>%s</a>',
        e($class),
        e($pageUrl($target)),
        e($paginationEndpoint),
        $target,
        e($paginationForm),
        e($paginationTarget),
        e($paginationTarget),
        e($pageUrl($target)),
        $aria !== '' ? ' aria-label="' . e($aria) . '"' : '',
        e($label)
    );
};
?>
<nav class="pagination is-small is-centered mt-4" role="navigation" aria-label="Paginación">
    <?php if ($page > 1): ?>
        <?= $link($page - 1, 'Anterior', 'pagination-previous', 'Página anterior') ?>
    <?php else: ?>
        <a class="pagination-previous is-disabled" aria-disabled="true">Anterior</a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <?= $link($page + 1, 'Siguiente', 'pagination-next', 'Página siguiente') ?>
    <?php else: ?>
        <a class="pagination-next is-disabled" aria-disabled="true">Siguiente</a>
    <?php endif; ?>

    <ul class="pagination-list">
        <?php $previous = 0; ?>
        <?php foreach ($visible as $n): ?>
            <?php if ($n - $previous > 1): ?>
                <li><span class="pagination-ellipsis">&hellip;</span></li>
            <?php endif; ?>
            <li>
                <?php if ($n === $page): ?>
                    <a class="pagination-link is-current" aria-current="page" aria-label="Página <?= $n ?>"><?= $n ?></a>
                <?php else: ?>
                    <?= $link($n, (string) $n, 'pagination-link', 'Ir a la página ' . $n) ?>
                <?php endif; ?>
            </li>
            <?php $previous = $n; ?>
        <?php endforeach; ?>
    </ul>
</nav>
<p class="has-text-centered has-text-grey is-size-7 mt-2">Página <?= $page ?> de <?= $pages ?></p>
