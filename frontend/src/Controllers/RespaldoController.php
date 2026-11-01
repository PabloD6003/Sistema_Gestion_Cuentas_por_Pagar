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

final class RespaldoController
{
    private const TIPOS = ['COMPLETO', 'DIFERENCIAL', 'LOG'];

    public function index(Request $request, array $params = []): Response
    {
        $this->requireAdmin();
        $items = [];
        $error = null;

        try {
            $items = ApiClient::forSession()->get('/api/respaldos')['items'] ?? [];
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $error = $exception->getMessage();
        }

        return Response::html(View::render('respaldos/index', [
            'pageTitle' => 'Respaldos',
            'items' => $items,
            'error' => $error,
        ]));
    }

    public function store(Request $request, array $params = []): Response
    {
        $this->requireAdmin();
        $tipo = strtoupper(trim((string) ($request->post('tipo') ?? '')));

        if (!in_array($tipo, self::TIPOS, true)) {
            flash('error', 'Seleccione un tipo de respaldo válido.');
            return Response::redirect('/respaldos');
        }

        try {
            // BACKUP + RESTORE VERIFYONLY puede tardar más que una consulta normal
            $respaldo = ApiClient::forSession()->post('/api/respaldos', ['tipo' => $tipo], 120);
            flash('success', sprintf(
                'Respaldo %s generado y verificado: %s',
                strtolower($tipo),
                (string) ($respaldo['archivo'] ?? '')
            ));
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            flash('error', $exception->getMessage());
        }

        return Response::redirect('/respaldos');
    }

    private function requireAdmin(): void
    {
        Auth::requireLogin();

        if (!Auth::isAdmin()) {
            throw new HttpException('Solo un administrador puede gestionar los respaldos.', 403);
        }
    }
}
