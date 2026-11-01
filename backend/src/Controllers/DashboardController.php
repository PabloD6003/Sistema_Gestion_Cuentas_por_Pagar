<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\CatalogoRepository;
use App\Repositories\CuentaPagarRepository;
use App\Security\Auth;

final class DashboardController
{
    private CatalogoRepository $catalogos;

    public function __construct()
    {
        $this->catalogos = new CatalogoRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        Auth::requireLogin();
        (new CuentaPagarRepository())->refreshOverdue();

        return Response::json($this->catalogos->dashboard());
    }
}
