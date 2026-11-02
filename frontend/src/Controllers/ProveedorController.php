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

final class ProveedorController
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('proveedores/index', [
            'pageTitle' => 'Proveedores',
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'filters' => $this->filters($request),
            'old' => [],
            'error' => null,
        ]));
    }

    public function partial(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $data = $this->fetch($request);

        return Response::html(View::render('proveedores/_table', [
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'oob' => true,
        ], null));
    }

    /** Resultado de la consulta a Hacienda para el formulario de proveedor (HTMX). */
    public function hacienda(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $identificacion = preg_replace('/\D+/', '', (string) $request->query('identificacion_tributaria', ''));

        if ($identificacion === '') {
            return Response::html('');
        }

        try {
            $resultado = ApiClient::forSession()->get('/api/hacienda/contribuyentes/' . $identificacion);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $formato = $exception->statusCode() === 422;
            $resultado = [
                'estado' => $formato ? 'formato' : 'no_disponible',
                'mensaje' => $formato
                    ? 'La identificación debe tener entre 9 y 12 dígitos para consultarla en Hacienda.'
                    : 'No se pudo consultar Hacienda en este momento. Puede continuar con el registro.',
                'contribuyente' => null,
            ];
        }

        return Response::html(View::render('proveedores/_hacienda', ['resultado' => $resultado], null));
    }

    public function store(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $old = $request->postAll();

        try {
            $body = $this->payload($old);
            $created = ApiClient::forSession()->post('/api/proveedores', $body);
            $name = $created['proveedor']['razon_social'] ?? $body['razon_social'];
            flash('success', 'Proveedor registrado: ' . $name);

            return Response::redirect('/proveedores');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $error = $exception->getMessage();
        }

        $data = $this->fetch($request);

        return Response::html(View::render('proveedores/index', [
            'pageTitle' => 'Proveedores',
            'items' => $data['items'] ?? [],
            'total' => $data['total'] ?? 0,
            'filters' => $this->filters($request),
            'old' => $old,
            'error' => $error,
        ]), 422);
    }

    public function show(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $api = ApiClient::forSession();

        try {
            $proveedor = $api->get('/api/proveedores/' . $id);
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 404) {
                throw new HttpException('Proveedor no encontrado.', 404);
            }
            throw $exception;
        }

        $historial = $api->get('/api/proveedores/' . $id . '/historial');
        $evaluaciones = $api->get('/api/proveedores/' . $id . '/evaluaciones');

        return Response::html(View::render('proveedores/show', [
            'pageTitle' => (string) ($proveedor['razon_social'] ?? 'Proveedor'),
            'proveedor' => $proveedor,
            'resumen' => $historial['resumen'] ?? null,
            'movimientos' => $historial['movimientos'] ?? [],
            'evaluaciones' => $evaluaciones['items'] ?? [],
        ]));
    }

    public function evaluar(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $id = $this->id($params);
        $calificacion = (int) ($request->post('calificacion') ?? 0);

        if ($calificacion < 1 || $calificacion > 5) {
            flash('error', 'Seleccione una calificación de 1 a 5.');
            return Response::redirect('/proveedores/' . $id);
        }

        try {
            ApiClient::forSession()->post('/api/proveedores/' . $id . '/evaluaciones', [
                'calificacion' => $calificacion,
                'comentario' => trim((string) ($request->post('comentario') ?? '')),
                'fecha_evaluacion' => trim((string) ($request->post('fecha_evaluacion') ?? '')),
            ]);
            flash('success', 'Evaluación registrada.');
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect('/proveedores/' . $id);
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

    /**
     * @return array{q: string, estado: string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'estado' => (string) $request->query('estado', ''),
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

        return ApiClient::forSession()->get('/api/proveedores?' . http_build_query($query));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function payload(array $input): array
    {
        $required = [
            'identificacion_tributaria' => 'La identificación tributaria',
            'razon_social' => 'La razón social',
            'pais' => 'El país',
            'direccion' => 'La dirección',
            'telefono' => 'El teléfono',
            'correo' => 'El correo',
            'banco' => 'El banco',
            'cuenta_bancaria' => 'La cuenta bancaria',
        ];

        foreach ($required as $field => $label) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                throw new ApiException($label . ' es obligatorio.', 422);
            }
        }

        $email = trim((string) ($input['correo'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException('El correo no tiene un formato válido.', 422);
        }

        return [
            'identificacion_tributaria' => trim((string) $input['identificacion_tributaria']),
            'razon_social' => trim((string) $input['razon_social']),
            'nombre_comercial' => trim((string) ($input['nombre_comercial'] ?? '')),
            'pais' => trim((string) $input['pais']),
            'direccion' => trim((string) $input['direccion']),
            'telefono' => trim((string) $input['telefono']),
            'correo' => $email,
            'contacto_nombre' => trim((string) ($input['contacto_nombre'] ?? '')),
            'dias_credito' => (int) ($input['dias_credito'] ?? 30),
            'banco' => trim((string) $input['banco']),
            'cuenta_bancaria' => trim((string) $input['cuenta_bancaria']),
            'estado' => (string) ($input['estado'] ?? '') === '1',
            'codigo_erp' => trim((string) ($input['codigo_erp'] ?? '')),
        ];
    }
}
