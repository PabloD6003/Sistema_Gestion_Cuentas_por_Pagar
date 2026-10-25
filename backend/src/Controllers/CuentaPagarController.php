<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\CuentaPagarRepository;
use App\Security\Auth;
use App\Support\Validator;

final class CuentaPagarController
{
    private CuentaPagarRepository $cuentas;

    public function __construct()
    {
        $this->cuentas = new CuentaPagarRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = min(100, max(1, (int) ($request->query('per_page', 20))));
        $estado = $request->query('estado');
        $proveedor = $request->query('id_proveedor');
        $vencidas = filter_var($request->query('vencidas', false), FILTER_VALIDATE_BOOLEAN);

        return Response::json($this->cuentas->paginate([
            'q' => $request->query('q'),
            'estado' => is_string($estado) && $estado !== '' ? $estado : null,
            'id_proveedor' => is_numeric($proveedor) ? (int) $proveedor : null,
            'vencidas' => $vencidas,
        ], $page, $perPage));
    }

    public function show(Request $request, array $params): Response
    {
        Auth::requireLogin();
        $cuenta = $this->cuentas->find($this->id($params));

        if ($cuenta === null) {
            throw new HttpException('Cuenta por pagar no encontrada.', 404);
        }

        return Response::json($cuenta);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = Auth::requireLogin();
        $data = $this->validated($request->json());
        $id = $this->cuentas->create($data, (int) $user['id_usuario']);

        return Response::json([
            'id_cuenta_pagar' => $id,
            'cuenta' => $this->cuentas->find($id),
        ], 201, ['Location' => '/api/cuentas/' . $id]);
    }

    public function annul(Request $request, array $params): Response
    {
        $user = Auth::requireLogin();
        $this->cuentas->annul($this->id($params), (int) $user['id_usuario']);

        return Response::noContent();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        Validator::required($data, ['id_proveedor', 'numero_factura', 'fecha_emision', 'detalle']);

        $detalle = Validator::array($data['detalle'], 'detalle');
        $lineas = [];

        foreach ($detalle as $index => $linea) {
            if (!is_array($linea)) {
                throw Validator::invalid('detalle', "La línea {$index} no es un objeto válido.");
            }

            Validator::required($linea, ['descripcion', 'cantidad', 'precio_unitario']);
            $pct = Validator::decimal($linea['pct_impuesto'] ?? 0, 'pct_impuesto', 0);
            if ($pct > 100) {
                throw Validator::invalid('pct_impuesto', 'El impuesto no puede superar 100.');
            }

            $lineas[] = [
                'descripcion' => Validator::string($linea['descripcion'], 'descripcion', 250),
                'cantidad' => Validator::decimal($linea['cantidad'], 'cantidad', 0.0001, 4),
                'precio_unitario' => Validator::decimal($linea['precio_unitario'], 'precio_unitario', 0, 4),
                'pct_impuesto' => $pct,
            ];
        }

        $pctDescuento = Validator::decimal($data['pct_descuento_pronto_pago'] ?? 0, 'pct_descuento_pronto_pago', 0);
        if ($pctDescuento > 100) {
            throw Validator::invalid('pct_descuento_pronto_pago', 'El descuento no puede superar 100.');
        }

        $diasDescuento = array_key_exists('dias_descuento', $data) && $data['dias_descuento'] !== null
            ? Validator::int($data['dias_descuento'], 'dias_descuento', 0)
            : null;

        if ($pctDescuento > 0 && $diasDescuento === null) {
            throw Validator::invalid('dias_descuento', 'Debe indicar los días del descuento por pronto pago.');
        }

        return [
            'id_proveedor' => Validator::int($data['id_proveedor'], 'id_proveedor', 1),
            'numero_factura' => Validator::string($data['numero_factura'], 'numero_factura', 50),
            'fecha_emision' => Validator::date($data['fecha_emision'], 'fecha_emision'),
            'detalle' => $lineas,
            'dias_credito' => array_key_exists('dias_credito', $data) && $data['dias_credito'] !== null
                ? Validator::int($data['dias_credito'], 'dias_credito', 0)
                : null,
            'pct_descuento_pronto_pago' => $pctDescuento,
            'dias_descuento' => $diasDescuento,
            'codigo_erp' => Validator::string($data['codigo_erp'] ?? null, 'codigo_erp', 50, false),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function id(array $params): int
    {
        return Validator::int($params['id'] ?? null, 'id', 1);
    }
}
