<?php
$visibleTotal = (int) ($total ?? 0);
$recordLabel = $visibleTotal === 1 ? 'registro' : 'registros';
?>
<?php if (!empty($oob)): ?>
    <span class="tag is-light" id="proveedores-total" hx-swap-oob="true"><?= e((string) $visibleTotal) ?> <?= $recordLabel ?></span>
<?php endif; ?>
<div class="table-container">
    <table class="table is-fullwidth is-hoverable is-narrow">
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>Contacto</th>
                <th>Crédito</th>
                <th>Banco / cuenta</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5">
                        <div class="app-empty-state py-6">
                            <p class="has-text-weight-semibold">No se encontraron proveedores.</p>
                            <p class="has-text-grey is-size-7">Ajuste los filtros o registre un proveedor nuevo.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['razon_social'] ?? '—') ?></strong>
                            <p class="is-size-7 has-text-grey"><?= e($item['identificacion_tributaria'] ?? '') ?></p>
                        </td>
                        <td>
                            <span><?= e($item['correo'] ?? '—') ?></span>
                            <p class="is-size-7 has-text-grey"><?= e($item['telefono'] ?? '') ?></p>
                        </td>
                        <td><?= e((string) ($item['dias_credito'] ?? 0)) ?> días</td>
                        <td>
                            <span><?= e($item['banco'] ?? '—') ?></span>
                            <p class="is-size-7 has-text-grey"><?= e($item['cuenta_bancaria'] ?? '') ?></p>
                        </td>
                        <td><?= state_badge(!empty($item['estado']) ? 'ACTIVO' : 'INACTIVO', !empty($item['estado']) ? 'Activo' : 'Inactivo') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
