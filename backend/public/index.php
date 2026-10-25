<?php

declare(strict_types=1);

use App\Config;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Support\Logger;
use App\Support\SqlError;

require __DIR__ . '/../bootstrap.php';

$request = Request::fromGlobals();

try {
    $method = $request->method();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        && $request->path() !== '/api/auth/login') {
        Csrf::validate($request);
    }

    /** @var App\Http\Router $router */
    $router = require __DIR__ . '/../src/routes.php';
    $response = $router->dispatch($request);
} catch (HttpException $exception) {
    $payload = ['error' => $exception->getMessage()];
    if ($exception->getDetails() !== []) {
        $payload['details'] = $exception->getDetails();
    }

    $response = Response::json($payload, $exception->getStatusCode(), $exception->getHeaders());
} catch (PDOException $exception) {
    Logger::error($exception, [
        'method' => $request->method(),
        'path' => $request->path(),
    ]);

    $message = Config::bool('APP_DEBUG')
        ? SqlError::message($exception)
        : 'No se pudo completar la operación en la base de datos.';

    $response = Response::json(['error' => $message], SqlError::status($exception));
} catch (Throwable $exception) {
    Logger::error($exception, [
        'method' => $request->method(),
        'path' => $request->path(),
    ]);

    $message = Config::bool('APP_DEBUG')
        ? $exception->getMessage()
        : 'Error interno del servidor.';

    $response = Response::json(['error' => $message], 500);
}

$response->send();
