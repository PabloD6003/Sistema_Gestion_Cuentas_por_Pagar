<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;

final class HealthController
{
    public function index(Request $request, array $params = []): Response
    {
        $database = Database::health();

        return Response::json([
            'status' => $database ? 'ok' : 'degraded',
            'service' => 'cxp-api',
            'database' => $database,
            'time' => date(DATE_ATOM),
        ], $database ? 200 : 503);
    }
}
