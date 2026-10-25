<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FALLO: ' . $message . PHP_EOL);
        exit(1);
    }
}

$router = new Router();

$router->get('/cuentas/nueva', static fn (Request $request): Response => Response::html('nueva'));
$router->get('/cuentas/{id}', static fn (Request $request, array $params): Response => Response::html('cuenta-' . $params['id']));
$router->post('/cuentas', static fn (Request $request): Response => Response::html('creada', 201));

$response = $router->dispatch(Request::create('GET', '/cuentas/nueva'));
check($response->status() === 200, 'GET /cuentas/nueva debe responder 200.');
check($response->body() === 'nueva', 'La ruta estática debe tener prioridad sobre {id}.');

$response = $router->dispatch(Request::create('GET', '/cuentas/42'));
check($response->status() === 200, 'GET /cuentas/42 debe responder 200.');
check($response->body() === 'cuenta-42', 'El parámetro {id} debe llegar al manejador.');

$response = $router->dispatch(Request::create('POST', '/cuentas'));
check($response->status() === 201, 'POST /cuentas debe responder 201.');
check($response->body() === 'creada', 'La ruta POST debe ejecutar su manejador.');

$notFound = false;
try {
    $router->dispatch(Request::create('GET', '/no-existe'));
} catch (App\Http\HttpException $exception) {
    $notFound = $exception->statusCode() === 404;
}
check($notFound, 'Una ruta inexistente debe lanzar 404.');

$methodNotAllowed = false;
try {
    $router->dispatch(Request::create('DELETE', '/cuentas'));
} catch (App\Http\HttpException $exception) {
    $methodNotAllowed = $exception->statusCode() === 405;
}
check($methodNotAllowed, 'Un método no permitido debe lanzar 405.');

echo "router_test: OK\n";
