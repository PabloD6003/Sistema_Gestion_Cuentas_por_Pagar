<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$publicRoot = realpath(__DIR__) ?: __DIR__;
$file = realpath(__DIR__ . $path);

if (
    $path !== '/'
    && $file !== false
    && is_file($file)
    && str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)
) {
    return false;
}

require __DIR__ . '/../bootstrap.php';

use App\ApiException;
use App\Auth;
use App\Http\HttpException;
use App\Http\RedirectException;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\View;

$request = Request::fromGlobals();

try {
    if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        Csrf::validate($request);
    }

    $router = require APP_BASE_PATH . '/routes.php';
    $response = $router->dispatch($request);
} catch (RedirectException $exception) {
    if ($exception->getMessage() !== '') {
        flash('warning', $exception->getMessage());
    }

    $response = $request->isHtmx()
        ? Response::html('', 200, ['HX-Redirect' => $exception->location()])
        : Response::redirect($exception->location());
} catch (HttpException $exception) {
    $status = $exception->statusCode();
    $message = $exception->getMessage();

    $response = Response::html(View::render('error', [
        'pageTitle' => 'Error ' . $status,
        'status' => $status,
        'message' => $message,
    ]), $status);
} catch (ApiException $exception) {
    if ($exception->statusCode() === 401) {
        unset($_SESSION['user']);
        flash('warning', 'Su sesión expiró. Inicie sesión nuevamente.');
        $response = $request->isHtmx()
            ? Response::html('', 200, ['HX-Redirect' => '/login'])
            : Response::redirect('/login');
    } else {
        error_log('API error: ' . $exception->getMessage());
        $status = $exception->statusCode() >= 400 && $exception->statusCode() < 600
            ? $exception->statusCode()
            : 502;

        $response = Response::html(View::render('error', [
            'pageTitle' => 'Error de integración',
            'status' => $status,
            'message' => $exception->getMessage(),
        ]), $status);
    }
} catch (Throwable $exception) {
    error_log('UI error: ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());

    $message = App\Config::bool('APP_DEBUG', false)
        ? $exception->getMessage()
        : 'Ocurrió un error inesperado. Revise el registro de la aplicación.';

    $response = Response::html(View::render('error', [
        'pageTitle' => 'Error interno',
        'status' => 500,
        'message' => $message,
    ]), 500);
}

$response->send();
