<?php

declare(strict_types=1);

namespace App\Controllers;

use App\ApiClient;
use App\ApiException;
use App\Auth;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\View;

final class ReporteController
{
    private const TIPOS = ['aging', 'pagos', 'cumplimiento', 'saldos', 'etl'];

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        [$html, $error] = $this->content('aging');

        return Response::html(View::render('reportes/index', [
            'pageTitle' => 'Reportes',
            'contenido' => $html,
            'error' => $error,
            'tipoActivo' => 'aging',
        ]));
    }

    public function partial(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $tipo = (string) ($params['tipo'] ?? '');

        if (!in_array($tipo, self::TIPOS, true)) {
            throw new HttpException('Reporte no encontrado.', 404);
        }

        [$html] = $this->content($tipo);

        return Response::html($html);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function content(string $tipo): array
    {
        try {
            $api = ApiClient::forSession();

            if ($tipo === 'etl') {
                $response = $api->get('/api/reportes/etl');
                $html = View::render('reportes/_etl', [
                    'etl' => $response['data'] ?? null,
                ], null);
            } else {
                $path = match ($tipo) {
                    'aging' => '/api/reportes/aging',
                    'pagos' => '/api/reportes/pagos-mes',
                    'cumplimiento' => '/api/reportes/cumplimiento',
                    'saldos' => '/api/reportes/saldos-proveedor',
                    default => '/api/reportes/aging',
                };

                $response = $api->get($path);
                $template = match ($tipo) {
                    'aging' => 'reportes/_aging',
                    'pagos' => 'reportes/_pagos',
                    'cumplimiento' => 'reportes/_cumplimiento',
                    'saldos' => 'reportes/_saldos',
                    default => 'reportes/_aging',
                };

                $html = View::render($template, [
                    'items' => $response['items'] ?? [],
                ], null);
            }

            return [$html, null];
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $html = View::render('partials/alert', [
                'type' => 'warning',
                'message' => 'No se pudo cargar el reporte: ' . $exception->getMessage(),
            ], null);

            return [$html, $exception->getMessage()];
        }
    }
}
