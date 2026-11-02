<?php
/** @var array<string, mixed> $resultado */
$estado = (string) ($resultado['estado'] ?? '');
$contribuyente = is_array($resultado['contribuyente'] ?? null) ? $resultado['contribuyente'] : null;
?>
<?php if ($estado === 'encontrado' && $contribuyente !== null): ?>
    <?php
    $situacion = $contribuyente['situacion'] ?? [];
    $principal = null;
    foreach ($contribuyente['actividades'] ?? [] as $actividad) {
        if (!empty($actividad['principal'])) {
            $principal = $actividad;
            break;
        }
    }
    $inscrito = strcasecmp((string) ($situacion['estado'] ?? ''), 'Inscrito') === 0;
    ?>
    <div class="notification is-light <?= $inscrito ? 'is-info' : 'is-warning' ?> p-3 mt-2 mb-0 is-size-7"
         data-hacienda-nombre="<?= e($contribuyente['nombre'] ?? '') ?>">
        <p class="has-text-weight-semibold mb-1"><?= e($contribuyente['nombre'] ?? '') ?></p>
        <p class="mb-2 has-text-grey-dark">
            <?= e($contribuyente['tipo_identificacion_descripcion'] ?? '') ?>
            <?php if (!empty($contribuyente['regimen'])): ?> · <?= e($contribuyente['regimen']) ?><?php endif; ?>
        </p>
        <div class="tags mb-2">
            <span class="tag <?= $inscrito ? 'is-success' : 'is-warning' ?> is-light"><?= e($situacion['estado'] ?? 'Sin estado') ?></span>
            <?php if (!empty($situacion['moroso'])): ?><span class="tag is-danger is-light">Moroso</span><?php endif; ?>
            <?php if (!empty($situacion['omiso'])): ?><span class="tag is-danger is-light">Omiso</span><?php endif; ?>
        </div>
        <?php if ($principal !== null): ?>
            <p class="mb-2">Actividad principal: <?= e($principal['codigo'] ?? '') ?> · <?= e($principal['descripcion'] ?? '') ?></p>
        <?php endif; ?>
        <button type="button" class="button is-small is-link is-light" data-hacienda-usar>Usar este nombre</button>
    </div>
<?php else: ?>
    <p class="help <?= $estado === 'no_disponible' ? 'has-text-grey' : 'is-warning' ?>"><?= e($resultado['mensaje'] ?? '') ?></p>
<?php endif; ?>
