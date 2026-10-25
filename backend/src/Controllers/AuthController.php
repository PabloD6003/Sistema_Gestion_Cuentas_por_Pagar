<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UsuarioRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Support\Validator;

final class AuthController
{
    private UsuarioRepository $usuarios;

    public function __construct()
    {
        $this->usuarios = new UsuarioRepository();
    }

    public function login(Request $request, array $params = []): Response
    {
        $data = $request->json();
        Validator::required($data, ['nombre_usuario', 'contrasena']);

        $username = Validator::string($data['nombre_usuario'], 'nombre_usuario', 50);
        $password = $data['contrasena'];

        if (!is_string($password) || $password === '' || strlen($password) > 128) {
            throw Validator::invalid('contrasena', 'La contraseña es obligatoria y no puede superar 128 caracteres.');
        }

        $result = $this->usuarios->authenticate($username, $password);

        if ($result['resultado'] === 'BLOQUEADO') {
            throw new HttpException('Usuario bloqueado por intentos fallidos. Contacte al administrador.', 423);
        }

        if ($result['resultado'] !== 'OK' || $result['id_usuario'] === null) {
            throw new HttpException('Usuario o contraseña incorrectos.', 401);
        }

        $profile = $this->usuarios->profile($result['id_usuario']);
        if ($profile === null) {
            throw new HttpException('El usuario no tiene un perfil válido.', 401);
        }

        Auth::login($profile);
        $csrf = Csrf::rotate();

        return Response::json([
            'user' => Auth::user(),
            'csrf_token' => $csrf,
        ]);
    }

    public function logout(Request $request, array $params = []): Response
    {
        Auth::logout();

        return Response::noContent();
    }

    public function me(Request $request, array $params = []): Response
    {
        $user = Auth::requireLogin();

        return Response::json([
            'user' => $user,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function changePassword(Request $request, array $params = []): Response
    {
        $user = Auth::requireLogin();
        $data = $request->json();
        Validator::required($data, ['contrasena_actual', 'contrasena_nueva']);

        $current = $data['contrasena_actual'];
        $new = $data['contrasena_nueva'];

        if (!is_string($current) || $current === '' || !is_string($new) || strlen($new) < 8) {
            throw Validator::invalid('contrasena_nueva', 'La contraseña nueva debe tener al menos 8 caracteres.');
        }

        $this->usuarios->changePassword((int) $user['id_usuario'], $current, $new);

        return Response::noContent();
    }
}
