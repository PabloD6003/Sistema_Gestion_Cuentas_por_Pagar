<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\ReporteRepository;
use App\Security\Auth;
use App\Support\Logger;
use App\Support\Validator;
use DateTimeImmutable;
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

    public function flujoCaja(Request $request, array $params = []): Response
    {
        Auth::requireLogin();

        $hoy = new DateTimeImmutable('today');
        $desde = Validator::date((string) ($request->query('desde') ?: $hoy->format('Y-m-d')), 'desde');
        $hasta = Validator::date((string) ($request->query('hasta') ?: $hoy->modify('+90 days')->format('Y-m-d')), 'hasta');
        $agrupacion = strtoupper((string) ($request->query('agrupacion') ?: 'SEMANA'));

        if ($hasta < $desde) {
            throw Validator::invalid('hasta', 'La fecha final no puede ser anterior a la inicial.');
        }

        if ((new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days > 366) {
            throw Validator::invalid('hasta', 'El rango no puede superar un año.');
        }

        if (!in_array($agrupacion, ['SEMANA', 'MES'], true)) {
            throw Validator::invalid('agrupacion', 'La agrupación debe ser SEMANA o MES.');
        }

        return Response::json([
            'desde' => $desde,
            'hasta' => $hasta,
            'agrupacion' => $agrupacion,
            'items' => $this->reportes->flujoCaja($desde, $hasta, $agrupacion),
        ]);
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
