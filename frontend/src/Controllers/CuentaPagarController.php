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

final class CuentaPagarController
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('cuentas/index', [
            'pageTitle' => 'Cuentas por pagar',
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'filters' => $this->filters($request),
        ]));
    }

    public function partial(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('cuentas/_table', [
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'oob' => true,
        ], null));
    }

    public function create(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $providers = ApiClient::forSession()->get('/api/proveedores?' . http_build_query([
            'per_page' => 100,
            'estado' => 1,
        ]));

        return Response::html(View::render('cuentas/create', [
            'pageTitle' => 'Nueva factura',
            'providers' => $providers['items'] ?? [],
            'old' => ['dias_credito' => 30, 'pct_descuento_pronto_pago' => 0, 'detalle' => []],
            'error' => null,
        ]));
    }

    public function store(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $old = $request->postAll();

        try {
            $body = $this->payload($old);
            $created = ApiClient::forSession()->post('/api/cuentas', $body);
            $id = (int) ($created['id_cuenta_pagar'] ?? 0);
            flash('success', 'Factura registrada correctamente.');

            return Response::redirect('/cuentas/' . $id);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $error = $exception->getMessage();
        }

        $providers = ApiClient::forSession()->get('/api/proveedores?' . http_build_query([
            'per_page' => 100,
            'estado' => 1,
        ]));

        return Response::html(View::render('cuentas/create', [
            'pageTitle' => 'Nueva factura',
            'providers' => $providers['items'] ?? [],
            'old' => $old,
            'error' => $error,
        ]), 422);
    }

    public function edit(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $cuenta = $this->fetchCuenta($id);

        if (empty($cuenta['editable'])) {
            flash('warning', 'La factura no se puede modificar: está anulada o tiene pagos vigentes.');
            return Response::redirect('/cuentas/' . $id);
        }

        $pct = (float) ($cuenta['pct_descuento_pronto_pago'] ?? 0);
        $diasDescuento = $pct > 0 && !empty($cuenta['fecha_limite_descuento'])
            ? (int) (new \DateTimeImmutable((string) $cuenta['fecha_emision']))
                ->diff(new \DateTimeImmutable((string) $cuenta['fecha_limite_descuento']))->days
            : '';

        return $this->renderForm($id, [
            'id_proveedor' => $cuenta['id_proveedor'] ?? '',
            'numero_factura' => $cuenta['numero_factura'] ?? '',
            'fecha_emision' => substr((string) ($cuenta['fecha_emision'] ?? ''), 0, 10),
            'dias_credito' => $cuenta['dias_credito'] ?? '',
            'pct_descuento_pronto_pago' => $pct,
            'dias_descuento' => $diasDescuento,
            'codigo_erp' => $cuenta['codigo_erp'] ?? '',
            'detalle' => array_map(static fn (array $linea): array => [
                'descripcion' => $linea['descripcion'] ?? '',
                'cantidad' => $linea['cantidad'] ?? 1,
                'precio_unitario' => $linea['precio_unitario'] ?? 0,
                'pct_impuesto' => $linea['pct_impuesto'] ?? 0,
            ], $cuenta['detalle'] ?? []),
        ], null);
    }

    public function update(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $old = $request->postAll();

        try {
            ApiClient::forSession()->put('/api/cuentas/' . $id, $this->payload($old));
            flash('success', 'Factura modificada. Totales y vencimiento recalculados.');

            return Response::redirect('/cuentas/' . $id);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            return $this->renderForm($id, $old, $exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string, mixed> $old
     */
    private function renderForm(int $id, array $old, ?string $error, int $status = 200): Response
    {
        $providers = ApiClient::forSession()->get('/api/proveedores?' . http_build_query([
            'per_page' => 100,
            'estado' => 1,
        ]));

        return Response::html(View::render('cuentas/create', [
            'pageTitle' => 'Modificar factura',
            'providers' => $providers['items'] ?? [],
            'old' => $old,
            'error' => $error,
            'cuentaId' => $id,
        ]), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchCuenta(int $id): array
    {
        try {
            return ApiClient::forSession()->get('/api/cuentas/' . $id);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 404) {
                throw new HttpException('Cuenta por pagar no encontrada.', 404);
            }
            throw $exception;
        }
    }

    public function show(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $api = ApiClient::forSession();

        try {
            $cuenta = $api->get('/api/cuentas/' . $id);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 404) {
                throw new HttpException('Cuenta por pagar no encontrada.', 404);
            }
            throw $exception;
        }

        $catalogos = $api->get('/api/catalogos');
        $movimientos = $api->get('/api/movimientos?' . http_build_query([
            'id_cuenta_pagar' => $id,
            'per_page' => 100,
        ]));

        return Response::html(View::render('cuentas/show', [
            'pageTitle' => 'Factura ' . ($cuenta['numero_factura'] ?? ('#' . $id)),
            'cuenta' => $cuenta,
            'movimientos' => $movimientos['items'] ?? [],
            'tiposPago' => $catalogos['tipos_pago'] ?? [],
        ]));
    }

    public function annul(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $motivo = trim((string) ($request->post('motivo') ?? ''));

        if ($motivo === '') {
            flash('error', 'Indique el motivo de la anulación.');
            return Response::redirect('/cuentas/' . $id);
        }

        try {
            ApiClient::forSession()->post('/api/cuentas/' . $id . '/anular', ['motivo' => $motivo]);
            flash('success', 'Factura anulada.');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect('/cuentas/' . $id);
    }

    /**
     * @return array{q: string, estado: string, vencidas: string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'estado' => (string) $request->query('estado', ''),
            'vencidas' => (string) $request->query('vencidas', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(Request $request): array
    {
        $filters = $this->filters($request);
        $query = ['per_page' => 50];

        if ($filters['q'] !== '') {
            $query['q'] = $filters['q'];
        }

        if ($filters['estado'] !== '') {
            $query['estado'] = $filters['estado'];
        }

        if ($filters['vencidas'] === '1') {
            $query['vencidas'] = 1;
        }

        return ApiClient::forSession()->get('/api/cuentas?' . http_build_query($query));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function payload(array $input): array
    {
        $provider = (int) ($input['id_proveedor'] ?? 0);
        if ($provider <= 0) {
            throw new ApiException('Seleccione un proveedor.', 422);
        }

        $invoice = trim((string) ($input['numero_factura'] ?? ''));
        if ($invoice === '') {
            throw new ApiException('El número de factura es obligatorio.', 422);
        }

        $date = trim((string) ($input['fecha_emision'] ?? ''));
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new ApiException('La fecha de emisión no es válida.', 422);
        }

        $lines = [];
        $rawLines = $input['detalle'] ?? [];
        if (!is_array($rawLines)) {
            $rawLines = [];
        }

        foreach ($rawLines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $description = trim((string) ($line['descripcion'] ?? ''));
            $quantity = (float) ($line['cantidad'] ?? 0);
            $price = (float) ($line['precio_unitario'] ?? 0);
            $tax = (float) ($line['pct_impuesto'] ?? 0);

            if ($description === '' && $quantity === 0.0 && $price === 0.0) {
                continue;
            }

            if ($description === '') {
                throw new ApiException('Cada línea debe tener una descripción.', 422);
            }

            if ($quantity <= 0) {
                throw new ApiException('La cantidad de cada línea debe ser mayor que cero.', 422);
            }

            if ($price < 0) {
                throw new ApiException('El precio unitario no puede ser negativo.', 422);
            }

            if ($tax < 0 || $tax > 100) {
                throw new ApiException('El porcentaje de impuesto debe estar entre 0 y 100.', 422);
            }

            $lines[] = [
                'descripcion' => $description,
                'cantidad' => $quantity,
                'precio_unitario' => $price,
                'pct_impuesto' => $tax,
            ];
        }

        if ($lines === []) {
            throw new ApiException('Agregue al menos una línea de detalle.', 422);
        }

        $creditDays = trim((string) ($input['dias_credito'] ?? ''));
        $discountPct = (float) ($input['pct_descuento_pronto_pago'] ?? 0);
        $discountDays = trim((string) ($input['dias_descuento'] ?? ''));

        if ($discountPct < 0 || $discountPct > 100) {
            throw new ApiException('El porcentaje de descuento debe estar entre 0 y 100.', 422);
        }

        if ($discountPct > 0 && $discountDays === '') {
            throw new ApiException('Indique los días del descuento por pronto pago.', 422);
        }

        return [
            'id_proveedor' => $provider,
            'numero_factura' => $invoice,
            'fecha_emision' => $date,
            'detalle' => $lines,
            'dias_credito' => $creditDays === '' ? null : (int) $creditDays,
            'pct_descuento_pronto_pago' => $discountPct,
            'dias_descuento' => $discountDays === '' ? null : (int) $discountDays,
            'codigo_erp' => trim((string) ($input['codigo_erp'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function id(array $params): int
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            throw new HttpException('Identificador inválido.', 404);
        }

        return $id;
    }
}
