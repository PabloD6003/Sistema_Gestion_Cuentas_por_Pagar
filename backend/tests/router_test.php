<?php

declare(strict_types=1);

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

require __DIR__ . '/../autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
}

$router = new Router();
$router->get('/api/test/{id}', static function (Request $request, array $params): Response {
    return Response::json(['id' => (int) $params['id'], 'method' => $request->method()]);
});
$router->post('/api/test', static function (): Response {
    return Response::json(['created' => true], 201);
});

$response = $router->dispatch(Request::create('GET', '/api/test/42'));
check($response->status() === 200, 'GET debe responder 200');
$payload = json_decode($response->body(), true);
check(($payload['id'] ?? null) === 42, 'El parámetro {id} debe llegar al controlador');
check(($payload['method'] ?? null) === 'GET', 'El método debe llegar al controlador');

$response = $router->dispatch(Request::create('POST', '/api/test'));
check($response->status() === 201, 'POST debe responder 201');
check(str_contains($response->body(), '"created":true'), 'El cuerpo JSON debe conservarse');

try {
    $router->dispatch(Request::create('GET', '/api/test'));
    check(false, 'Un método no permitido debe lanzar 405');
} catch (HttpException $exception) {
    check($exception->getStatusCode() === 405, 'El código debe ser 405');
    check(($exception->getHeaders()['Allow'] ?? '') === 'POST', 'Debe indicar el método permitido');
}

try {
    $router->dispatch(Request::create('GET', '/api/desconocido'));
    check(false, 'Una ruta inexistente debe lanzar 404');
} catch (HttpException $exception) {
    check($exception->getStatusCode() === 404, 'El código debe ser 404');
}

fwrite(STDOUT, "router_test: OK\n");
