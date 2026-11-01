<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Support\Cast;

final class RespaldoRepository
{
    public const TIPOS = ['COMPLETO', 'DIFERENCIAL', 'LOG'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $limit = 20): array
    {
        $rows = Database::call('EXEC dbo.sp_respaldos_listar @maximo = ?;', [$limit]);

        return Cast::rows($rows, ['id_respaldo'], ['tamano_mb']);
    }

    /**
     * Genera el respaldo con BACKUP y lo verifica con RESTORE VERIFYONLY.
     *
     * @return array<string, mixed>
     */
    public function create(string $tipo, ?string $ruta, int $userId): array
    {
        $rows = Database::call(
            'SET NOCOUNT ON;
             EXEC dbo.sp_respaldo_base @tipo = ?, @ruta = ?, @id_usuario = ?;',
            [$tipo, $ruta, $userId]
        );

        return Cast::row($rows[0] ?? [], [], ['tamano_mb']);
    }
}
