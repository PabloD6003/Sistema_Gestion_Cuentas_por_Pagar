<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\MovimientoRepository;
use App\Security\Auth;
use App\Support\Validator;

final class MovimientoController
{
    private MovimientoRepository $movimientos;

    public function __construct()
    {
        $this->movimientos = new MovimientoRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        $page = max(1, (int) ($request->query('page', 1)));
        $perPage = min(100, max(1, (int) ($request->query('per_page', 20))));
        $cuenta = $request->query('id_cuenta_pagar');
        $conciliado = $request->query('conciliado');
        $conciliadoFilter = null;

        if ($conciliado !== null && $conciliado !== '') {
            $conciliadoFilter = filter_var($conciliado, FILTER_VALIDATE_BOOLEAN);
        }

        return Response::json($this->movimientos->paginate([
            'id_cuenta_pagar' => is_numeric($cuenta) ? (int) $cuenta : null,
            'estado' => $request->query('estado'),
            'tipo' => $request->query('tipo'),
            'conciliado' => $conciliadoFilter,
            'q' => $request->query('q'),
        ], $page, $perPage));
    }

    public function storePago(Request $request, array $params = []): Response
    {
        $user = Auth::requireLogin();
        $data = $request->json();
        Validator::required($data, ['id_cuenta_pagar', 'monto', 'id_tipo_pago']);

        $fechaPago = null;
        if (isset($data['fecha_pago']) && $data['fecha_pago'] !== '') {
            $fechaPago = Validator::date($data['fecha_pago'], 'fecha_pago');
        }

        $idProgramacion = null;
        if (isset($data['id_programacion']) && $data['id_programacion'] !== '') {
            $idProgramacion = Validator::int($data['id_programacion'], 'id_programacion', 1);
        }

        $result = $this->movimientos->createPayment([
            'id_cuenta_pagar' => Validator::int($data['id_cuenta_pagar'], 'id_cuenta_pagar', 1),
            'monto' => Validator::decimal($data['monto'], 'monto', 0.01),
            'id_tipo_pago' => Validator::int($data['id_tipo_pago'], 'id_tipo_pago', 1),
            'fecha_pago' => $fechaPago,
            'referencia' => Validator::string($data['referencia'] ?? null, 'referencia', 50, false),
            'descripcion' => Validator::string($data['descripcion'] ?? null, 'descripcion', 150, false),
            'aplicar_descuento' => Validator::bool($data['aplicar_descuento'] ?? true, true) ? 1 : 0,
            'id_programacion' => $idProgramacion,
        ], (int) $user['id_usuario']);

        return Response::json($result, 201);
    }

    public function approve(Request $request, array $params): Response
    {
        $user = Auth::requireApprove();
        $this->movimientos->approve($this->id($params), (int) $user['id_usuario']);

        return Response::noContent();
    }

    public function annulPago(Request $request, array $params): Response
    {
        $user = Auth::requireApprove();
        $data = $request->json();
        Validator::required($data, ['motivo']);
        $motivo = Validator::string($data['motivo'], 'motivo', 150);
        $this->movimientos->annulPayment($this->id($params), $motivo, (int) $user['id_usuario']);

        return Response::noContent();
    }

    public function annulMovimiento(Request $request, array $params): Response
    {
        $user = Auth::requireApprove();
        $data = $request->json();
        Validator::required($data, ['motivo']);
        $motivo = Validator::string($data['motivo'], 'motivo', 150);
        $this->movimientos->annulMovement($this->id($params), $motivo, (int) $user['id_usuario']);

        return Response::noContent();
    }

    public function lotes(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        return Response::json([
            'items' => $this->movimientos->lots(100),
        ]);
    }

    public function generarLote(Request $request, array $params = []): Response
    {
        $user = Auth::requireApprove();
        $data = $request->json();
        Validator::required($data, ['id_banco']);
        $idBanco = Validator::int($data['id_banco'], 'id_banco', 1);
        $idLote = $this->movimientos->generateLot($idBanco, (int) $user['id_usuario']);

        return Response::json([
            'id_lote' => $idLote,
        ], 201, ['Location' => '/api/lotes/' . $idLote]);
    }

    public function cambiarEstadoLote(Request $request, array $params): Response
    {
        $user = Auth::requireApprove();
        $data = $request->json();
        Validator::required($data, ['estado']);
        $estado = Validator::string($data['estado'], 'estado', 20);

        if (!in_array($estado, ['ENVIADO', 'PROCESADO', 'RECHAZADO'], true)) {
            throw Validator::invalid('estado', 'Estado de lote no permitido.');
        }

        $this->movimientos->changeLotState($this->id($params), $estado, (int) $user['id_usuario']);

        return Response::noContent();
    }

    /**
     * @param array<string, string> $params
     */
    private function id(array $params): int
    {
        return Validator::int($params['id'] ?? null, 'id', 1);
    }
}
