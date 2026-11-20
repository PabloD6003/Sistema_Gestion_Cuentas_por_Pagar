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
    /** Tramos válidos de antigüedad, iguales a los que calcula el ETL. */
    private const BUCKETS = ['0-30', '31-60', '61-90', '90+'];

    private ReporteRepository $reportes;

    public function __construct()
    {
        $this->reportes = new ReporteRepository();
    }

    public function aging(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $buscar = $this->texto($request->query('buscar'));
        $bucket = $this->texto($request->query('bucket'));

        if ($bucket !== null && !in_array($bucket, self::BUCKETS, true)) {
            throw Validator::invalid('bucket', 'El tramo debe ser 0-30, 31-60, 61-90 o 90+.');
        }

        return $this->safe(fn (): array => $this->reportes->aging($buscar, $bucket));
    }

    public function pagosMes(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        [$desde, $hasta] = $this->rangoMeses($request);

        return $this->safe(fn (): array => $this->reportes->pagosMes($desde, $hasta));
    }

    public function cumplimiento(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        [$desde, $hasta] = $this->rangoMeses($request);

        return $this->safe(fn (): array => $this->reportes->cumplimiento($desde, $hasta));
    }

    public function saldosProveedor(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $buscar = $this->texto($request->query('buscar'));
        $minimo = $this->texto($request->query('saldo_minimo'));
        $saldoMinimo = $minimo === null
            ? null
            : (float) Validator::decimal($minimo, 'saldo_minimo', 0);

        return $this->safe(fn (): array => $this->reportes->saldosProveedor($buscar, $saldoMinimo));
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

    /**
     * Ejecuta el ETL completo del Data Warehouse bajo demanda (etl.sp_ejecutar_todo).
     * Solo el administrador: es una tarea de operación, igual que los respaldos.
     */
    public function ejecutarEtl(Request $request, array $params = []): Response
    {
        $user = Auth::requireAdmin();

        return Response::json($this->reportes->ejecutarEtl((int) $user['id_usuario']), 201);
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

    /** Un filtro de texto vacío equivale a no filtrar. */
    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * Rango de meses en formato YYYY-MM; ambos extremos son opcionales.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function rangoMeses(Request $request): array
    {
        $desde = $this->mes($request->query('desde'), 'desde');
        $hasta = $this->mes($request->query('hasta'), 'hasta');

        if ($desde !== null && $hasta !== null && $hasta < $desde) {
            throw Validator::invalid('hasta', 'El mes final no puede ser anterior al inicial.');
        }

        return [$desde, $hasta];
    }

    private function mes(mixed $valor, string $campo): ?string
    {
        $mes = $this->texto($valor);

        if ($mes !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) !== 1) {
            throw Validator::invalid($campo, 'El mes debe tener el formato AAAA-MM.');
        }

        return $mes;
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
