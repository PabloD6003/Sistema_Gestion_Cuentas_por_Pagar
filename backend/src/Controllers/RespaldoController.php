<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\RespaldoRepository;
use App\Security\Auth;
use App\Support\Validator;

final class RespaldoController
{
    private RespaldoRepository $respaldos;

    public function __construct()
    {
        $this->respaldos = new RespaldoRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireAdmin();

        return Response::json([
            'items' => $this->respaldos->list(20),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $user = Auth::requireAdmin();
        $data = $request->json();
        $tipo = strtoupper((string) Validator::string($data['tipo'] ?? 'COMPLETO', 'tipo', 12));

        if (!in_array($tipo, RespaldoRepository::TIPOS, true)) {
            throw Validator::invalid('tipo', 'El tipo de respaldo debe ser COMPLETO, DIFERENCIAL o LOG.');
        }

        // Carpeta opcional en el servidor de SQL Server; vacía usa la predeterminada de la instancia
        $ruta = Config::get('BACKUP_PATH');
        $ruta = is_string($ruta) && trim($ruta) !== '' ? trim($ruta) : null;

        return Response::json($this->respaldos->create($tipo, $ruta, (int) $user['id_usuario']), 201);
    }
}
