<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\ReporteRepository;
use App\Security\Auth;
use App\Support\Logger;
use PDOException;

final class ReporteController
{
    private ReporteRepository $reportes;

    public function __construct()
    {
        $this->reportes = new ReporteRepository();
    }

    public function aging(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        return $this->safe(fn (): array => $this->reportes->aging());
    }

    public function pagosMes(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        return $this->safe(fn (): array => $this->reportes->pagosMes());
    }

    public function cumplimiento(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        return $this->safe(fn (): array => $this->reportes->cumplimiento());
    }

    public function saldosProveedor(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        return $this->safe(fn (): array => $this->reportes->saldosProveedor());
    }

    public function etl(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        try {
            return Response::json([
                'data' => $this->reportes->etlUltimaCarga(),
            ]);
        } catch (PDOException $exception) {
            Logger::error($exception);
            throw new HttpException('El Data Warehouse no está disponible. Ejecute el ETL y verifique la conexión.', 503);
        }
    }

    /**
     * @param callable(): array<int, array<string, mixed>> $callback
     */
    private function safe(callable $callback): Response
    {
        try {
            return Response::json(['items' => $callback()]);
        } catch (PDOException $exception) {
            Logger::error($exception);
            throw new HttpException('El Data Warehouse no está disponible. Ejecute el ETL y verifique la conexión.', 503);
        }
    }
}
