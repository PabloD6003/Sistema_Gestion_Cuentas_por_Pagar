<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Support\Logger;
use RuntimeException;

/**
 * Consulta pública de contribuyentes del Ministerio de Hacienda (GET /fe/ae).
 *
 * La API no está documentada oficialmente, así que la respuesta se lee de forma defensiva y
 * se traduce a un estado simple:
 * - encontrado:    200 con los datos del contribuyente.
 * - no_encontrado: 404, la identificación no está inscrita.
 * - invalido:      400 o 403 (Akamai devuelve una página HTML cuando rechaza el número).
 * - no_disponible: tiempo agotado, error de red, 5xx o una respuesta que no se entiende.
 * Hacienda nunca bloquea el registro del proveedor: quien consume el resultado decide.
 */
final class HaciendaClient
{
    public const ENCONTRADO = 'encontrado';
    public const NO_ENCONTRADO = 'no_encontrado';
    public const INVALIDO = 'invalido';
    public const NO_DISPONIBLE = 'no_disponible';

    private const TIPOS_IDENTIFICACION = [
        '01' => 'Persona física',
        '02' => 'Persona jurídica',
        '03' => 'DIMEX',
        '04' => 'NITE',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
        private readonly ?string $caBundle = null
    ) {
    }

    public static function fromConfig(): self
    {
        $caBundle = Config::get('HACIENDA_CA_BUNDLE');

        return new self(
            (string) Config::get('HACIENDA_API_URL', 'https://api.hacienda.go.cr/fe/ae'),
            max(1, Config::int('HACIENDA_TIMEOUT', 5)),
            $caBundle === null || $caBundle === '' ? null : $caBundle
        );
    }

    /** Hacienda solo acepta dígitos: "3-101-000046" se consulta como "3101000046". */
    public static function normalizar(string $identificacion): string
    {
        return (string) preg_replace('/\D+/', '', $identificacion);
    }

    /** Física 9 dígitos, jurídica y NITE 10, DIMEX 11 o 12. */
    public static function formatoValido(string $identificacion): bool
    {
        return preg_match('/^\d{9,12}$/', $identificacion) === 1;
    }

    /**
     * @return array{estado: string, identificacion: string, mensaje: string, contribuyente: array<string, mixed>|null}
     */
    public function consultar(string $identificacion): array
    {
        $identificacion = self::normalizar($identificacion);
        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extensión cURL de PHP no está habilitada en el servidor de la API.');
        }

        $handle = curl_init($this->baseUrl . '?' . http_build_query(['identificacion' => $identificacion]));
        if ($handle === false) {
            return self::resultado(self::NO_DISPONIBLE, $identificacion);
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        // PHP en Windows no trae certificados raíz: se usa el almacén de Windows o, si se
        // indica, el archivo HACIENDA_CA_BUNDLE. La verificación TLS nunca se desactiva.
        if ($this->caBundle !== null) {
            curl_setopt($handle, CURLOPT_CAINFO, $this->caBundle);
        } elseif (defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        if ($body === false) {
            Logger::error(new RuntimeException('Hacienda no respondió: ' . $error), ['identificacion' => $identificacion]);

            return self::resultado(self::NO_DISPONIBLE, $identificacion);
        }

        return self::interpretar($identificacion, $status, (string) $body);
    }

    /**
     * Traduce la respuesta HTTP de Hacienda. Es pública y sin red para poder probarla.
     *
     * @return array{estado: string, identificacion: string, mensaje: string, contribuyente: array<string, mixed>|null}
     */
    public static function interpretar(string $identificacion, int $status, string $body): array
    {
        if ($status === 404) {
            return self::resultado(self::NO_ENCONTRADO, $identificacion);
        }

        if ($status === 400 || $status === 403) {
            return self::resultado(self::INVALIDO, $identificacion);
        }

        $json = json_decode($body, true);
        if ($status !== 200 || !is_array($json) || !is_string($json['nombre'] ?? null)) {
            return self::resultado(self::NO_DISPONIBLE, $identificacion);
        }

        return self::resultado(self::ENCONTRADO, $identificacion, self::contribuyente($json));
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    private static function contribuyente(array $json): array
    {
        $tipo = is_string($json['tipoIdentificacion'] ?? null) ? $json['tipoIdentificacion'] : '';
        $regimen = is_array($json['regimen'] ?? null) ? $json['regimen'] : [];
        $situacion = is_array($json['situacion'] ?? null) ? $json['situacion'] : [];

        $actividades = [];
        foreach (is_array($json['actividades'] ?? null) ? $json['actividades'] : [] as $actividad) {
            if (!is_array($actividad)) {
                continue;
            }
            $actividades[] = [
                'codigo' => (string) ($actividad['codigo'] ?? ''),
                'descripcion' => trim((string) ($actividad['descripcion'] ?? '')),
                'principal' => ($actividad['tipo'] ?? '') === 'P',
                'activa' => ($actividad['estado'] ?? '') === 'A',
            ];
        }

        return [
            'nombre' => trim($json['nombre']),
            'tipo_identificacion' => $tipo,
            'tipo_identificacion_descripcion' => self::TIPOS_IDENTIFICACION[$tipo] ?? 'Desconocido',
            'regimen' => isset($regimen['descripcion']) ? (string) $regimen['descripcion'] : null,
            'situacion' => [
                'estado' => isset($situacion['estado']) ? (string) $situacion['estado'] : null,
                'moroso' => strtoupper((string) ($situacion['moroso'] ?? '')) === 'SI',
                'omiso' => strtoupper((string) ($situacion['omiso'] ?? '')) === 'SI',
                'administracion_tributaria' => isset($situacion['administracionTributaria'])
                    ? (string) $situacion['administracionTributaria']
                    : null,
            ],
            'actividades' => $actividades,
        ];
    }

    /**
     * @param array<string, mixed>|null $contribuyente
     * @return array{estado: string, identificacion: string, mensaje: string, contribuyente: array<string, mixed>|null}
     */
    private static function resultado(string $estado, string $identificacion, ?array $contribuyente = null): array
    {
        $mensajes = [
            self::ENCONTRADO => 'Contribuyente encontrado en Hacienda.',
            self::NO_ENCONTRADO => 'La identificación no está inscrita en Hacienda.',
            self::INVALIDO => 'Hacienda indica que la identificación no es válida.',
            self::NO_DISPONIBLE => 'No se pudo consultar Hacienda en este momento. Puede continuar con el registro.',
        ];

        return [
            'estado' => $estado,
            'identificacion' => $identificacion,
            'mensaje' => $mensajes[$estado],
            'contribuyente' => $contribuyente,
        ];
    }
}
