<?php

declare(strict_types=1);

use App\Config;

require __DIR__ . '/autoload.php';

define('APP_BASE_PATH', __DIR__);

Config::load(__DIR__);

date_default_timezone_set('America/Costa_Rica');
error_reporting(E_ALL);
ini_set('display_errors', Config::bool('APP_DEBUG') ? '1' : '0');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443');

session_name(Config::get('SESSION_NAME', 'cxp_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
