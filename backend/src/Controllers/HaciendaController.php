<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Security\Auth;
use App\Services\HaciendaClient;
use App\Support\Validator;

final class HaciendaController
{
    /**
     * Consulta un contribuyente en Hacienda. Responde 200 con el estado de la consulta
     * (encontrado, no_encontrado, invalido) y 503 si Hacienda no está disponible.
     */
    public function contribuyente(Request $request, array $params): Response
    {
        Auth::requireLogin();

        $identificacion = HaciendaClient::normalizar((string) ($params['identificacion'] ?? ''));
        if (!HaciendaClient::formatoValido($identificacion)) {
            throw Validator::invalid('identificacion', 'La identificación debe tener entre 9 y 12 dígitos.');
        }

        $resultado = HaciendaClient::fromConfig()->consultar($identificacion);
        $status = $resultado['estado'] === HaciendaClient::NO_DISPONIBLE ? 503 : 200;

        return Response::json($resultado, $status);
    }
}
