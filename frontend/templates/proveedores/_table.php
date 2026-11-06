<?php
$visibleTotal = (int) ($total ?? 0);
$recordLabel = $visibleTotal === 1 ? 'registro' : 'registros';
?>
<?php if (!empty($oob)): ?>
    <span class="tag is-light" id="proveedores-total" role="status" aria-live="polite" hx-swap-oob="true"><?= e((string) $visibleTotal) ?> <?= $recordLabel ?></span>
<?php endif; ?>
<div class="table-container">
    <table class="table is-fullwidth is-hoverable is-narrow app-table app-proveedores-table">
        <thead>
            <tr>
                <th scope="col">Proveedor</th>
                <th scope="col">Contacto</th>
                <th scope="col" class="has-text-right">Crédito</th>
                <th scope="col">Evaluación comercial</th>
                <th scope="col">Estado</th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6">
                        <div class="app-empty-state py-6">
                            <p class="has-text-weight-semibold">No se encontraron proveedores.</p>
                            <p class="has-text-grey is-size-7">Ajuste los filtros o registre un proveedor nuevo.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $idProveedor = (int) ($item['id_proveedor'] ?? 0);
                    $evaluaciones = (int) ($item['evaluaciones'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <a href="/proveedores/<?= $idProveedor ?>" class="has-text-weight-semibold"><?= e($item['razon_social'] ?? '—') ?></a>
                            <p class="is-size-7 has-text-grey app-nowrap"><?= e($item['identificacion_tributaria'] ?? '') ?></p>
                        </td>
                        <td>
                            <span><?= e($item['contacto_nombre'] ?? $item['correo'] ?? '—') ?></span>
                            <p class="is-size-7 has-text-grey"><?= e($item['correo'] ?? '') ?> · <span class="app-nowrap"><?= e($item['telefono'] ?? '') ?></span></p>
                        </td>
                        <td class="has-text-right app-nowrap"><?= e((string) ($item['dias_credito'] ?? 0)) ?> días</td>
                        <td class="app-nowrap">
                            <?= rating_stars($item['calificacion_promedio'] ?? null) ?>
                            <?php if ($evaluaciones > 0): ?>
                                <p class="is-size-7 has-text-grey">
                                    <?= e(number_format((float) $item['calificacion_promedio'], 2)) ?> · <?= $evaluaciones ?> <?= $evaluaciones === 1 ? 'reseña' : 'reseñas' ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td><?= state_badge(!empty($item['estado']) ? 'ACTIVO' : 'INACTIVO', !empty($item['estado']) ? 'Activo' : 'Inactivo') ?></td>
                        <td class="has-text-right">
                            <a class="button is-small is-link is-light app-nowrap" href="/proveedores/<?= $idProveedor ?>">Expediente y reseñas</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
