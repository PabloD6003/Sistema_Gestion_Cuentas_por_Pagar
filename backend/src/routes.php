<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CatalogoController;
use App\Controllers\CuentaPagarController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\MovimientoController;
use App\Controllers\ProveedorController;
use App\Controllers\ReporteController;
use App\Http\Router;

$router = new Router();

$router->get('/api/health', [HealthController::class, 'index']);

$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);
$router->post('/api/auth/change-password', [AuthController::class, 'changePassword']);

$router->get('/api/proveedores', [ProveedorController::class, 'index']);
$router->post('/api/proveedores', [ProveedorController::class, 'store']);
$router->get('/api/proveedores/{id}', [ProveedorController::class, 'show']);
$router->put('/api/proveedores/{id}', [ProveedorController::class, 'update']);

$router->get('/api/cuentas', [CuentaPagarController::class, 'index']);
$router->post('/api/cuentas', [CuentaPagarController::class, 'store']);
$router->get('/api/cuentas/{id}', [CuentaPagarController::class, 'show']);
$router->post('/api/cuentas/{id}/anular', [CuentaPagarController::class, 'annul']);

$router->get('/api/movimientos', [MovimientoController::class, 'index']);
$router->post('/api/movimientos/{id}/anular', [MovimientoController::class, 'annulMovimiento']);
$router->post('/api/pagos', [MovimientoController::class, 'storePago']);
$router->post('/api/pagos/{id}/aprobar', [MovimientoController::class, 'approve']);
$router->post('/api/pagos/{id}/anular', [MovimientoController::class, 'annulPago']);

$router->get('/api/lotes', [MovimientoController::class, 'lotes']);
$router->post('/api/lotes', [MovimientoController::class, 'generarLote']);
$router->post('/api/lotes/{id}/estado', [MovimientoController::class, 'cambiarEstadoLote']);

$router->get('/api/catalogos', [CatalogoController::class, 'index']);
$router->get('/api/dashboard', [DashboardController::class, 'index']);

$router->get('/api/reportes/aging', [ReporteController::class, 'aging']);
$router->get('/api/reportes/pagos-mes', [ReporteController::class, 'pagosMes']);
$router->get('/api/reportes/cumplimiento', [ReporteController::class, 'cumplimiento']);
$router->get('/api/reportes/saldos-proveedor', [ReporteController::class, 'saldosProveedor']);
$router->get('/api/reportes/etl', [ReporteController::class, 'etl']);

return $router;
