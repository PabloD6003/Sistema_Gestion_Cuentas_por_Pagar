<?php
$appName = (string) \App\Config::get('APP_NAME', 'Cuentas por Pagar');
$auth = \App\Auth::check();
$user = \App\Auth::user();
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$flashes = take_flashes();

$navItems = [
    ['path' => '/dashboard', 'label' => 'Panel', 'hint' => 'Resumen general'],
    ['path' => '/proveedores', 'label' => 'Proveedores', 'hint' => 'Catálogo y crédito'],
    ['path' => '/cuentas', 'label' => 'Cuentas por pagar', 'hint' => 'Facturas y saldos'],
    ['path' => '/pagos', 'label' => 'Pagos', 'hint' => 'Aprobación y conciliación'],
    ['path' => '/reportes', 'label' => 'Reportes', 'hint' => 'Data Warehouse'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Inicio') ?> · <?= e($appName) ?></title>
    <meta name="csrf-token" content="<?= e(\App\Security\Csrf::token()) ?>">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/vendor/bulma.min.css">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/vendor/htmx.min.js" defer></script>
    <script src="/assets/app.js" defer></script>
</head>
<body data-currency="<?= e((string) \App\Config::get('CURRENCY_SYMBOL', '₡')) ?>" class="<?= $auth ? 'app-body' : 'login-body' ?>">
<?php if ($auth): ?>
    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="app-brand">
                <span class="app-brand-mark">CxP</span>
                <div>
                    <p class="app-brand-title"><?= e($appName) ?></p>
                    <p class="app-brand-subtitle">Gestión financiera</p>
                </div>
            </div>

            <nav class="menu app-menu">
                <p class="menu-label">Operación</p>
                <ul class="menu-list">
                    <?php foreach ($navItems as $item): ?>
                        <?php $active = is_active_path($item['path'], $currentPath); ?>
                        <li>
                            <a href="<?= e($item['path']) ?>" class="<?= $active ? 'is-active' : '' ?>">
                                <span><?= e($item['label']) ?></span>
                                <small><?= e($item['hint']) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="app-sidebar-footer">
                <span class="tag is-dark">OLTP + DW</span>
                <p>Universidad · Proyecto de bases de datos</p>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <div>
                    <p class="app-topbar-eyebrow">Sistema de cuentas por pagar</p>
                    <h1 class="app-topbar-title"><?= e($pageTitle ?? 'Inicio') ?></h1>
                </div>
                <div class="app-user">
                    <div class="app-user-copy">
                        <strong><?= e($user['nombre_completo'] ?? $user['nombre_usuario'] ?? 'Usuario') ?></strong>
                        <span><?= e($user['rol'] ?? '') ?></span>
                    </div>
                    <span class="app-avatar"><?= e(strtoupper(substr((string) ($user['nombre_usuario'] ?? 'U'), 0, 1))) ?></span>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button class="button is-small is-light" type="submit">Salir</button>
                    </form>
                </div>
            </header>

            <nav class="app-mobile-nav">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= e($item['path']) ?>" class="<?= is_active_path($item['path'], $currentPath) ? 'is-active' : '' ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="section app-content">
                <div class="container is-fluid">
                    <?php foreach ($flashes as $flash): ?>
                        <?php
                        $flashType = match ((string) $flash['type']) {
                            'success' => 'success',
                            'error', 'danger' => 'danger',
                            'warning' => 'warning',
                            'link' => 'link',
                            default => 'info',
                        };
                        ?>
                        <div class="notification is-<?= e($flashType) ?> app-flash">
                            <button class="delete" type="button" aria-label="Cerrar"></button>
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>

                    <?= $content ?>
                </div>
            </main>
        </div>
    </div>
<?php else: ?>
    <main class="login-shell">
        <?= $content ?>
    </main>
<?php endif; ?>
</body>
</html>
