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
    private const TIPOS = ['aging', 'pagos', 'cumplimiento', 'saldos', 'flujo', 'etl'];

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $tipo = (string) $request->query('tipo', 'aging');
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'aging';
        }

        [$html, $error] = $this->content($tipo);

        return Response::html(View::render('reportes/index', [
            'pageTitle' => 'Reportes',
            'contenido' => $html,
            'error' => $error,
            'tipoActivo' => $tipo,
        ]));
    }

    public function partial(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $tipo = (string) ($params['tipo'] ?? '');

        if (!in_array($tipo, self::TIPOS, true)) {
            throw new HttpException('Reporte no encontrado.', 404);
        }

        [$html] = $this->content($tipo, $this->filtros($request));

        return Response::html($html);
    }

    /**
     * Ejecuta el ETL del Data Warehouse a pedido del administrador y vuelve a la pestaña
     * "Estado ETL" con el resultado.
     */
    public function ejecutarEtl(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        if (!Auth::isAdmin()) {
            throw new HttpException('Solo un administrador puede ejecutar el ETL.', 403);
        }

        try {
            // El ETL completo tarda más que una consulta normal
            $resultado = ApiClient::forSession()->post('/api/etl/ejecutar', [], 300);
            flash('success', sprintf(
                'ETL ejecutado correctamente (ejecución #%d). Los reportes ya incluyen los datos registrados hasta ahora.',
                (int) ($resultado['id_ejecucion'] ?? 0)
            ));
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', 'El ETL no pudo completarse: ' . $exception->getMessage());
        }

        return Response::redirect('/reportes?tipo=etl');
    }

    /**
     * @param array<string, string> $query
     * @return array{0: string, 1: string|null}
     */
    /**
     * Filtros que puede llevar cualquier reporte. Los vacíos se descartan más abajo.
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        $filtros = [];
        foreach (['desde', 'hasta', 'agrupacion', 'buscar', 'bucket', 'saldo_minimo'] as $campo) {
            $filtros[$campo] = trim((string) $request->query($campo, ''));
        }

        return $filtros;
    }

    private function content(string $tipo, array $query = []): array
    {
        try {
            $api = ApiClient::forSession();

            if ($tipo === 'flujo') {
                // Proyección desde el OLTP: no depende de la última carga del ETL
                $response = $api->get('/api/reportes/flujo-caja?' . http_build_query(array_filter($query)));
                $html = View::render('reportes/_flujo', [
                    'items' => $response['items'] ?? [],
                    'desde' => $response['desde'] ?? '',
                    'hasta' => $response['hasta'] ?? '',
                    'agrupacion' => $response['agrupacion'] ?? 'SEMANA',
                ], null);
            } elseif ($tipo === 'etl') {
                $response = $api->get('/api/reportes/etl');
                $html = View::render('reportes/_etl', [
                    'etl' => $response['data'] ?? null,
                    'puedeEjecutar' => Auth::isAdmin(),
                ], null);
            } else {
                $path = match ($tipo) {
                    'aging' => '/api/reportes/aging',
                    'pagos' => '/api/reportes/pagos-mes',
                    'cumplimiento' => '/api/reportes/cumplimiento',
                    'saldos' => '/api/reportes/saldos-proveedor',
                    default => '/api/reportes/aging',
                };

                // Cada reporte acepta solo sus filtros; el resto se descarta
                $admitidos = match ($tipo) {
                    'aging' => ['buscar', 'bucket'],
                    'pagos', 'cumplimiento' => ['desde', 'hasta'],
                    'saldos' => ['buscar', 'saldo_minimo'],
                    default => [],
                };
                $filtros = array_filter(
                    array_intersect_key($query, array_flip($admitidos)),
                    static fn ($valor): bool => $valor !== '' && $valor !== null
                );

                $response = $api->get($path . ($filtros === [] ? '' : '?' . http_build_query($filtros)));
                $template = match ($tipo) {
                    'aging' => 'reportes/_aging',
                    'pagos' => 'reportes/_pagos',
                    'cumplimiento' => 'reportes/_cumplimiento',
                    'saldos' => 'reportes/_saldos',
                    default => 'reportes/_aging',
                };

                $html = View::render($template, [
                    'items' => $response['items'] ?? [],
                    'filtros' => $filtros,
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
