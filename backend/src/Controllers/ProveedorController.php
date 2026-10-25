<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\ProveedorRepository;
use App\Security\Auth;
use App\Support\Validator;

final class ProveedorController
{
    private ProveedorRepository $proveedores;

    public function __construct()
    {
        $this->proveedores = new ProveedorRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = min(100, max(1, (int) ($request->query('per_page', 20))));
        $estado = $request->query('estado');
        $estadoFilter = null;

        if ($estado !== null && $estado !== '') {
            $estadoFilter = in_array((string) $estado, ['1', 'true', 'activo'], true) ? 1 : 0;
        }

        return Response::json($this->proveedores->paginate([
            'q' => $request->query('q'),
            'estado' => $estadoFilter,
        ], $page, $perPage));
    }

    public function show(Request $request, array $params): Response
    {
        Auth::requireLogin();
        $proveedor = $this->proveedores->find($this->id($params));

        if ($proveedor === null) {
            throw new HttpException('Proveedor no encontrado.', 404);
        }

        return Response::json($proveedor);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = Auth::requireLogin();
        $data = $this->validated($request->json());
        $id = $this->proveedores->create($data, (int) $user['id_usuario']);

        return Response::json([
            'id_proveedor' => $id,
            'proveedor' => $this->proveedores->find($id),
        ], 201, ['Location' => '/api/proveedores/' . $id]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = Auth::requireLogin();
        $id = $this->id($params);
        $data = $this->validated($request->json());
        $this->proveedores->update($id, $data, (int) $user['id_usuario']);

        return Response::json([
            'id_proveedor' => $id,
            'proveedor' => $this->proveedores->find($id),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        Validator::required($data, [
            'identificacion_tributaria',
            'razon_social',
            'pais',
            'direccion',
            'telefono',
            'correo',
            'banco',
            'cuenta_bancaria',
        ]);

        $correo = (string) $data['correo'];
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw Validator::invalid('correo', 'El correo no tiene un formato válido.');
        }

        return [
            'identificacion_tributaria' => Validator::string($data['identificacion_tributaria'], 'identificacion_tributaria', 30),
            'razon_social' => Validator::string($data['razon_social'], 'razon_social', 150),
            'nombre_comercial' => Validator::string($data['nombre_comercial'] ?? null, 'nombre_comercial', 150, false),
            'pais' => Validator::string($data['pais'], 'pais', 60),
            'direccion' => Validator::string($data['direccion'], 'direccion', 250),
            'telefono' => Validator::string($data['telefono'], 'telefono', 30),
            'correo' => $correo,
            'contacto_nombre' => Validator::string($data['contacto_nombre'] ?? null, 'contacto_nombre', 120, false),
            'dias_credito' => Validator::int($data['dias_credito'] ?? 30, 'dias_credito', 0),
            'banco' => Validator::string($data['banco'], 'banco', 80),
            'cuenta_bancaria' => Validator::string($data['cuenta_bancaria'], 'cuenta_bancaria', 34),
            'estado' => Validator::bool($data['estado'] ?? true, true) ? 1 : 0,
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
