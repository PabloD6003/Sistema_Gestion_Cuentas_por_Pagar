<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = strtolower((string) Config::get('DB_DRIVER', 'sqlsrv'));
        $host = (string) Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT');
        if ($port !== null && $port !== '') {
            $host .= ',' . $port;
        }

        $database = (string) Config::get('DB_NAME', 'CuentasPorPagarDB');
        $trusted = Config::bool('DB_TRUSTED', false);
        $user = Config::get('DB_USER');
        $password = Config::get('DB_PASSWORD');

        $dsn = match ($driver) {
            'odbc' => self::odbcDsn($host, $database, $trusted),
            'sqlsrv' => self::sqlsrvDsn($host, $database),
            default => throw new RuntimeException("Driver de base de datos no soportado: {$driver}"),
        };

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO(
                $dsn,
                $trusted ? null : $user,
                $trusted ? null : $password,
                $options
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'No se pudo conectar a SQL Server. Verifique DB_DRIVER, DB_HOST, DB_NAME y las extensiones PDO.',
                0,
                $e
            );
        }

        return self::$pdo;
    }

    private static function odbcDsn(string $host, string $database, bool $trusted): string
    {
        $driver = (string) Config::get('DB_ODBC_DRIVER', 'ODBC Driver 18 for SQL Server');
        $driver = trim($driver, '{}');

        $parts = [
            'Driver={' . $driver . '}',
            'Server=' . $host,
            'Database=' . $database,
            'Encrypt=' . (Config::bool('DB_ENCRYPT', true) ? 'yes' : 'no'),
            'TrustServerCertificate=' . (Config::bool('DB_TRUST_SERVER_CERTIFICATE', true) ? 'yes' : 'no'),
            'LoginTimeout=5',
        ];

        if ($trusted) {
            $parts[] = 'Trusted_Connection=yes';
        }

        return 'odbc:' . implode(';', $parts) . ';';
    }

    private static function sqlsrvDsn(string $host, string $database): string
    {
        return sprintf(
            'sqlsrv:Server=%s;Database=%s;Encrypt=%d;TrustServerCertificate=%d',
            $host,
            $database,
            Config::bool('DB_ENCRYPT', true) ? 1 : 0,
            Config::bool('DB_TRUST_SERVER_CERTIFICATE', true) ? 1 : 0
        );
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $statement = self::run($sql, $params);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = self::run($sql, $params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    /**
     * Ejecuta un procedimiento que termina con un SELECT y devuelve sus filas.
     *
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function call(string $sql, array $params = []): array
    {
        $statement = self::run($sql, $params);

        do {
            try {
                $rows = $statement->fetchAll();
            } catch (PDOException $e) {
                if (str_contains(strtolower($e->getMessage()), 'invalid cursor state')) {
                    return [];
                }
                throw $e;
            }

            if ($rows !== []) {
                return $rows;
            }

            try {
                $hasMore = $statement->nextRowset();
            } catch (PDOException $e) {
                if (str_contains(strtolower($e->getMessage()), 'invalid cursor state')) {
                    return [];
                }
                throw $e;
            }
        } while ($hasMore);

        return [];
    }

    /**
     * @param array<int, mixed> $params
     */
    private static function run(string $sql, array $params): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function health(): bool
    {
        try {
            self::fetchOne('SELECT 1 AS ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
