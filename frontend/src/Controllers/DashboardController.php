<?php

declare(strict_types=1);

namespace App\Controllers;

use App\ApiClient;
use App\ApiException;
use App\Auth;
use App\Http\Request;
use App\Http\Response;
use App\View;

final class DashboardController
{
    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        $api = ApiClient::forSession();

        $summary = $api->get('/api/dashboard');
        $recent = $api->get('/api/cuentas?' . http_build_query([
            'per_page' => 6,
            'estado' => 'PENDIENTE',
        ]));

        $aging = [];
        $etl = null;
        $warning = null;

        try {
            $agingResponse = $api->get('/api/reportes/aging');
            $aging = $agingResponse['items'] ?? [];
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $warning = 'No se pudo cargar la antigüedad desde el Data Warehouse: ' . $exception->getMessage();
        }

        try {
            $etlResponse = $api->get('/api/reportes/etl');
            $etl = $etlResponse['data'] ?? null;
        } catch (ApiException $exception) {
            if ($exception->statusCode() === 401) {
                throw $exception;
            }

            $etl = null;
        }

        $buckets = [
            '0-30' => 0.0,
            '31-60' => 0.0,
            '61-90' => 0.0,
            '90+' => 0.0,
        ];

        foreach ($aging as $row) {
            $bucket = (string) ($row['bucket_antiguedad'] ?? '0-30');
            if (!array_key_exists($bucket, $buckets)) {
                $buckets[$bucket] = 0.0;
            }
            $buckets[$bucket] += (float) ($row['saldo_pendiente'] ?? 0);
        }

        $bucketTotal = array_sum($buckets);

        return Response::html(View::render('dashboard', [
            'pageTitle' => 'Panel',
            'summary' => $summary,
            'recent' => $recent['items'] ?? [],
            'buckets' => $buckets,
            'bucketTotal' => $bucketTotal,
            'etl' => $etl,
            'warning' => $warning,
        ]));
    }
}
