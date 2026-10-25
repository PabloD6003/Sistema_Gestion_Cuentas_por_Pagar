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

final class PagoController
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('pagos/index', [
            'pageTitle' => 'Pagos',
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'filters' => $this->filters($request),
            'canApprove' => Auth::canApprove(),
        ]));
    }

    public function partial(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('pagos/_table', [
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'canApprove' => Auth::canApprove(),
            'oob' => true,
        ], null));
    }

    public function store(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $cuentaId = (int) ($params['id'] ?? 0);

        if ($cuentaId <= 0) {
            throw new HttpException('Cuenta por pagar inválida.', 404);
        }

        $monto = (float) ($request->post('monto') ?? 0);
        $tipoPago = (int) ($request->post('id_tipo_pago') ?? 0);

        if ($monto <= 0) {
            flash('error', 'El monto del pago debe ser mayor que cero.');
            return Response::redirect('/cuentas/' . $cuentaId);
        }

        if ($tipoPago <= 0) {
            flash('error', 'Seleccione un tipo de pago.');
            return Response::redirect('/cuentas/' . $cuentaId);
        }

        $body = [
            'id_cuenta_pagar' => $cuentaId,
            'monto' => $monto,
            'id_tipo_pago' => $tipoPago,
            'fecha_pago' => trim((string) ($request->post('fecha_pago') ?? '')) ?: null,
            'referencia' => trim((string) ($request->post('referencia') ?? '')),
            'descripcion' => trim((string) ($request->post('descripcion') ?? '')),
            'aplicar_descuento' => (string) ($request->post('aplicar_descuento') ?? '') === '1',
        ];

        try {
            $created = ApiClient::forSession()->post('/api/pagos', $body);
            $discount = (float) ($created['monto_descuento'] ?? 0);
            $message = 'Pago registrado.';
            if ($discount > 0) {
                $message .= ' Descuento por pronto pago aplicado: ' . money($discount) . '.';
            }
            flash('success', $message);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect('/cuentas/' . $cuentaId);
    }

    public function approve(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $returnTo = $this->returnTo($request);

        try {
            ApiClient::forSession()->post('/api/pagos/' . $id . '/aprobar');
            flash('success', 'Pago aprobado. El saldo de la factura fue actualizado.');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect($returnTo);
    }

    public function annul(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $returnTo = $this->returnTo($request);
        $motivo = trim((string) ($request->post('motivo') ?? ''));

        if ($motivo === '') {
            flash('error', 'Indique el motivo de la anulación.');
            return Response::redirect($returnTo);
        }

        try {
            ApiClient::forSession()->post('/api/pagos/' . $id . '/anular', ['motivo' => $motivo]);
            flash('success', 'Pago anulado. El saldo de la factura fue restaurado.');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect($returnTo);
    }

    /**
     * @return array{q: string, estado: string, tipo: string, conciliado: string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'estado' => (string) $request->query('estado', ''),
            'tipo' => (string) $request->query('tipo', ''),
            'conciliado' => (string) $request->query('conciliado', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(Request $request): array
    {
        $filters = $this->filters($request);
        $query = ['per_page' => 50];

        foreach (['q', 'estado', 'tipo'] as $key) {
            if ($filters[$key] !== '') {
                $query[$key] = $filters[$key];
            }
        }

        if ($filters['conciliado'] !== '') {
            $query['conciliado'] = $filters['conciliado'];
        }

        return ApiClient::forSession()->get('/api/movimientos?' . http_build_query($query));
    }

    /**
     * @param array<string, string> $params
     */
    private function id(array $params): int
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            throw new HttpException('Movimiento inválido.', 404);
        }

        return $id;
    }

    private function returnTo(Request $request): string
    {
        $returnTo = (string) ($request->post('return_to') ?? '');

        if (preg_match('#^/(?:cuentas/[1-9][0-9]*|pagos)$#', $returnTo) !== 1) {
            return '/pagos';
        }

        return $returnTo;
    }
}
