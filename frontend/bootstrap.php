<?php

declare(strict_types=1);

define('APP_BASE_PATH', __DIR__);

require __DIR__ . '/autoload.php';

App\Config::load(__DIR__ . '/.env');

date_default_timezone_set((string) App\Config::get('APP_TIMEZONE', 'America/Costa_Rica'));

$debug = App\Config::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/app.log');

$cookieDir = __DIR__ . '/storage/cookies';
if (!is_dir($cookieDir)) {
    mkdir($cookieDir, 0775, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) App\Config::get('SESSION_NAME', 'cxp_ui_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
