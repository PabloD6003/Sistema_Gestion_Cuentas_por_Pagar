<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class UsuarioRepository
{
    /**
     * @return array{id_usuario: int|null, resultado: string}
     */
    public function authenticate(string $username, string $password): array
    {
        $rows = Database::call(
            "SET NOCOUNT ON;
             DECLARE @id_usuario INT, @resultado VARCHAR(20);
             EXEC dbo.sp_usuario_autenticar
                 @nombre_usuario = ?,
                 @contrasena = ?,
                 @id_usuario = @id_usuario OUTPUT,
                 @resultado = @resultado OUTPUT;
             SELECT @id_usuario AS id_usuario, @resultado AS resultado;",
            [$username, $password]
        );

        $row = $rows[0] ?? [];

        return [
            'id_usuario' => isset($row['id_usuario']) ? (int) $row['id_usuario'] : null,
            'resultado' => (string) ($row['resultado'] ?? 'INVALIDO'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(int $id): ?array
    {
        $row = Database::fetchOne(
            'SELECT u.id_usuario, u.id_rol, u.rol, u.nombre_usuario, u.nombre_completo,
                    u.correo, u.estado, r.puede_aprobar_pagos
             FROM dbo.vw_usuarios u
             JOIN dbo.vw_roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = ?',
            [$id]
        );

        if ($row === null) {
            return null;
        }

        return Cast::row($row, ['id_usuario', 'id_rol'], [], ['estado', 'puede_aprobar_pagos']);
    }

    public function changePassword(int $id, string $current, string $new): void
    {
        Database::execute(
            'EXEC dbo.sp_usuario_cambiar_contrasena @id_usuario = ?, @contrasena_actual = ?, @contrasena_nueva = ?;',
            [$id, $current, $new]
        );
    }
}
