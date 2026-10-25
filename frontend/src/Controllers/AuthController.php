<?php

declare(strict_types=1);

namespace App\Controllers;

use App\ApiClient;
use App\ApiException;
use App\Auth;
use App\Http\Request;
use App\Http\Response;
use App\View;

final class AuthController
{
    public function showLogin(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return Response::redirect('/dashboard');
        }

        return Response::html(View::render('login', [
            'pageTitle' => 'Ingresar',
            'error' => null,
            'username' => '',
            'loggedOut' => (string) $request->query('logout', '') === '1',
        ]));
    }

    public function login(Request $request, array $params = []): Response
    {
        if (Auth::check()) {
            return Response::redirect('/dashboard');
        }

        $username = trim((string) $request->post('nombre_usuario', ''));
        $password = (string) $request->post('contrasena', '');

        if ($username === '' || $password === '') {
            return $this->renderLogin('Ingrese su usuario y contraseña.', $username);
        }

        try {
            $data = ApiClient::forSession()->login($username, $password);
            Auth::login($data);

            return Response::redirect('/dashboard');
        } catch (ApiException $exception) {
            return $this->renderLogin($exception->getMessage(), $username);
        }
    }

    public function logout(Request $request, array $params = []): Response
    {
        try {
            ApiClient::forSession()->logout();
        } catch (\Throwable) {
            // La limpieza local se realiza de todas formas.
        }

        Auth::logout();

        return Response::redirect('/login?logout=1');
    }

    private function renderLogin(string $error, string $username): Response
    {
        return Response::html(View::render('login', [
            'pageTitle' => 'Ingresar',
            'error' => $error,
            'username' => $username,
            'loggedOut' => false,
        ]));
    }
}
