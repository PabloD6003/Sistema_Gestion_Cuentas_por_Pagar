<?php $type = in_array(($type ?? 'info'), ['success', 'danger', 'warning', 'info', 'link'], true) ? $type : 'info'; ?>
<div class="notification is-<?= e($type) ?>">
    <?= e($message ?? '') ?>
</div>
