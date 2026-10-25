<?php

declare(strict_types=1);

use App\Auth;
use App\Controllers\AuthController;
use App\Controllers\CuentaPagarController;
use App\Controllers\DashboardController;
use App\Controllers\PagoController;
use App\Controllers\ProveedorController;
use App\Controllers\ReporteController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$router = new Router();

$router->get('/', static function (Request $request): Response {
    return Response::redirect(Auth::check() ? '/dashboard' : '/login');
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/proveedores', [ProveedorController::class, 'index']);
$router->post('/proveedores', [ProveedorController::class, 'store']);
$router->get('/partials/proveedores', [ProveedorController::class, 'partial']);

$router->get('/cuentas', [CuentaPagarController::class, 'index']);
$router->get('/cuentas/nueva', [CuentaPagarController::class, 'create']);
$router->get('/cuentas/{id}', [CuentaPagarController::class, 'show']);
$router->post('/cuentas', [CuentaPagarController::class, 'store']);
$router->post('/cuentas/{id}/pagos', [PagoController::class, 'store']);
$router->post('/cuentas/{id}/anular', [CuentaPagarController::class, 'annul']);
$router->get('/partials/cuentas', [CuentaPagarController::class, 'partial']);

$router->get('/pagos', [PagoController::class, 'index']);
$router->get('/partials/pagos', [PagoController::class, 'partial']);
$router->post('/pagos/{id}/aprobar', [PagoController::class, 'approve']);
$router->post('/pagos/{id}/anular', [PagoController::class, 'annul']);

$router->get('/reportes', [ReporteController::class, 'index']);
$router->get('/partials/reportes/{tipo}', [ReporteController::class, 'partial']);

return $router;
