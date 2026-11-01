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

/**
 * Lotes de transferencia y conciliación bancaria (tesorero y administrador).
 */
final class TesoreriaController
{
    private const ESTADOS_LOTE = ['ENVIADO', 'PROCESADO', 'RECHAZADO'];

    public function index(Request $request, array $params = []): Response
    {
        $this->requireTesoreria();
        $api = ApiClient::forSession();

        $bancos = array_values(array_filter(
            $api->get('/api/catalogos')['bancos'] ?? [],
            static fn (array $banco): bool => !empty($banco['estado'])
        ));
        $idBanco = (int) ($request->query('banco') ?? 0);
        if ($idBanco <= 0 && $bancos !== []) {
            $idBanco = (int) $bancos[0]['id_banco'];
        }

        $pendientes = $idBanco > 0 ? $api->get('/api/movimientos?' . http_build_query([
            'id_banco' => $idBanco,
            'tipo' => 'Pago',
            'estado' => 'APROBADO',
            'conciliado' => 0,
            'per_page' => 50,
        ])) : [];

        $conciliacion = $_SESSION['tesoreria_conciliacion'] ?? null;
        unset($_SESSION['tesoreria_conciliacion']);

        return Response::html(View::render('tesoreria/index', [
            'pageTitle' => 'Tesorería',
            'bancos' => $bancos,
            'idBanco' => $idBanco,
            'lotes' => $api->get('/api/lotes')['items'] ?? [],
            'pendientes' => $pendientes['items'] ?? [],
            'totalPendientes' => (int) ($pendientes['total'] ?? 0),
            'conciliacion' => is_array($conciliacion) ? $conciliacion : null,
        ]));
    }

    public function generarLote(Request $request, array $params = []): Response
    {
        $this->requireTesoreria();
        $idBanco = (int) ($request->post('id_banco') ?? 0);

        try {
            $lote = ApiClient::forSession()->post('/api/lotes', ['id_banco' => $idBanco]);
            flash('success', 'Lote #' . (int) ($lote['id_lote'] ?? 0) . ' generado con los pagos aprobados pendientes del banco.');
        } catch (ApiException $exception) {
            $this->rethrowSession($exception);
            flash('error', $exception->getMessage());
        }

        return Response::redirect('/tesoreria?banco=' . $idBanco);
    }

    public function cambiarEstadoLote(Request $request, array $params = []): Response
    {
        $this->requireTesoreria();
        $id = (int) ($params['id'] ?? 0);
        $estado = strtoupper(trim((string) ($request->post('estado') ?? '')));

        if ($id <= 0 || !in_array($estado, self::ESTADOS_LOTE, true)) {
            flash('error', 'Cambio de estado de lote no válido.');
            return Response::redirect('/tesoreria');
        }

        try {
            ApiClient::forSession()->post('/api/lotes/' . $id . '/estado', ['estado' => $estado]);
            flash('success', 'Lote #' . $id . ' marcado como ' . strtolower($estado) . '.');
        } catch (ApiException $exception) {
            $this->rethrowSession($exception);
            flash('error', $exception->getMessage());
        }

        return Response::redirect('/tesoreria');
    }

    public function conciliar(Request $request, array $params = []): Response
    {
        $this->requireTesoreria();
        $idBanco = (int) ($request->post('id_banco') ?? 0);
        $tolerancia = (int) ($request->post('tolerancia_dias') ?? 3);

        try {
            $lineas = self::parseExtracto((string) ($request->post('extracto') ?? ''));
            $resultado = ApiClient::forSession()->post('/api/conciliaciones', [
                'id_banco' => $idBanco,
                'tolerancia_dias' => $tolerancia,
                'lineas' => $lineas,
            ]);

            $_SESSION['tesoreria_conciliacion'] = [
                'items' => $resultado['items'] ?? [],
                'conciliados' => (int) ($resultado['conciliados'] ?? 0),
            ];
            flash('success', sprintf(
                'Extracto procesado: %d de %d líneas conciliadas.',
                (int) ($resultado['conciliados'] ?? 0),
                count($lineas)
            ));
        } catch (ApiException $exception) {
            $this->rethrowSession($exception);
            flash('error', $exception->getMessage());
        }

        return Response::redirect('/tesoreria?banco=' . $idBanco);
    }

    /**
     * Convierte el texto pegado del extracto (una línea por movimiento: fecha, referencia y
     * monto separados por tabulador, punto y coma o coma) en líneas para la API.
     *
     * @return array<int, array{fecha: string, referencia: string, monto: float}>
     */
    public static function parseExtracto(string $texto): array
    {
        $lineas = [];

        foreach (preg_split('/\R/', trim($texto)) ?: [] as $numero => $fila) {
            $fila = trim($fila);
            if ($fila === '') {
                continue;
            }

            $separador = str_contains($fila, "\t") ? "\t" : (str_contains($fila, ';') ? ';' : ',');
            $campos = array_map('trim', explode($separador, $fila));

            $fecha = self::fecha($campos[0] ?? '');
            if ($fecha === null) {
                // Encabezado del extracto ("Fecha;Referencia;Monto")
                if ($lineas === [] && !preg_match('/\d/', $campos[0] ?? '')) {
                    continue;
                }
                throw new ApiException('Línea ' . ($numero + 1) . ': la fecha debe ser AAAA-MM-DD o DD/MM/AAAA.', 422);
            }

            if (count($campos) < 3 || $campos[1] === '') {
                throw new ApiException('Línea ' . ($numero + 1) . ': indique fecha, referencia y monto.', 422);
            }

            $monto = str_replace(['₡', ' '], '', $campos[2]);
            if ($separador !== ',') {
                $monto = str_replace(',', '', $monto);
            }

            if (!is_numeric($monto) || (float) $monto <= 0) {
                throw new ApiException('Línea ' . ($numero + 1) . ': el monto no es válido.', 422);
            }

            $lineas[] = ['fecha' => $fecha, 'referencia' => $campos[1], 'monto' => round((float) $monto, 2)];
        }

        if ($lineas === []) {
            throw new ApiException('Pegue al menos una línea del extracto.', 422);
        }

        return $lineas;
    }

    private static function fecha(string $valor): ?string
    {
        foreach (['!Y-m-d', '!d/m/Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat($formato, $valor);
            if ($fecha !== false && $fecha->format(ltrim($formato, '!')) === $valor) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function requireTesoreria(): void
    {
        Auth::requireLogin();

        if (!Auth::canApprove()) {
            throw new HttpException('Solo tesorería y administración pueden gestionar lotes y conciliaciones.', 403);
        }
    }

    private function rethrowSession(ApiException $exception): void
    {
        if ($exception->statusCode() === 401) {
            throw $exception;
        }
    }
}
