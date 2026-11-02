<?php

declare(strict_types=1);

use App\Services\HaciendaClient;

require __DIR__ . '/../autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
}

check(HaciendaClient::normalizar('3-101-000046') === '3101000046', 'Se quitan los guiones');
check(HaciendaClient::normalizar(' 1 1111 1111 ') === '111111111', 'Se quitan los espacios');
check(HaciendaClient::formatoValido('111111111'), 'Cédula física de 9 dígitos');
check(HaciendaClient::formatoValido('155812345678'), 'DIMEX de 12 dígitos');
check(!HaciendaClient::formatoValido('12345678'), 'Menos de 9 dígitos no es válido');
check(!HaciendaClient::formatoValido('1234567890123'), 'Más de 12 dígitos no es válido');
check(!HaciendaClient::formatoValido(''), 'Vacío no es válido');

// Respuesta real de Hacienda para la CNFL (2026-11-01)
$cnfl = <<<'JSON'
{
  "nombre": "COMPAÑIA NACIONAL DE FUERZA Y LUZ SOCIEDAD ANONIMA",
  "tipoIdentificacion": "02",
  "regimen": { "codigo": 1, "descripcion": "Régimen general" },
  "situacion": { "moroso": "NO", "omiso": "NO", "estado": "Inscrito",
                 "administracionTributaria": "Dirección de Grandes Contribuyentes Nacionales" },
  "actividades": [
    { "estado": "A", "tipo": "P", "codigo": "3510.0",
      "descripcion": "Generación , transmisión y distribución de energía eléctrica" },
    { "estado": "I", "tipo": "S", "codigo": "4100.0", "descripcion": "Construcción de edificios" }
  ]
}
JSON;

$resultado = HaciendaClient::interpretar('3101000046', 200, $cnfl);
$contribuyente = $resultado['contribuyente'];
check($resultado['estado'] === HaciendaClient::ENCONTRADO, '200 con datos es encontrado');
check($contribuyente['nombre'] === 'COMPAÑIA NACIONAL DE FUERZA Y LUZ SOCIEDAD ANONIMA', 'Nombre del contribuyente');
check($contribuyente['tipo_identificacion_descripcion'] === 'Persona jurídica', 'Tipo 02 es persona jurídica');
check($contribuyente['regimen'] === 'Régimen general', 'Régimen');
check($contribuyente['situacion']['estado'] === 'Inscrito', 'Estado inscrito');
check($contribuyente['situacion']['moroso'] === false && $contribuyente['situacion']['omiso'] === false, 'No moroso ni omiso');
check(count($contribuyente['actividades']) === 2, 'Dos actividades');
check($contribuyente['actividades'][0]['principal'] && $contribuyente['actividades'][0]['activa'], 'Actividad principal activa');
check(!$contribuyente['actividades'][1]['principal'] && !$contribuyente['actividades'][1]['activa'], 'Actividad secundaria inactiva');

$moroso = HaciendaClient::interpretar('111111111', 200, '{"nombre":"X","situacion":{"moroso":"SI","omiso":"SI"}}');
check($moroso['contribuyente']['situacion']['moroso'] && $moroso['contribuyente']['situacion']['omiso'], 'Moroso y omiso');
check($moroso['contribuyente']['tipo_identificacion_descripcion'] === 'Desconocido', 'Sin tipo de identificación');
check($moroso['contribuyente']['actividades'] === [], 'Sin actividades');

$noEncontrado = '{"code":404,"status":"Information no available on this system, please try again with new values"}';
check(HaciendaClient::interpretar('888888888', 404, $noEncontrado)['estado'] === HaciendaClient::NO_ENCONTRADO, '404 es no encontrado');
check(HaciendaClient::interpretar('111111111', 400, '{"code":400}')['estado'] === HaciendaClient::INVALIDO, '400 es inválido');
check(HaciendaClient::interpretar('111111111', 403, '<html>Estimado usuario</html>')['estado'] === HaciendaClient::INVALIDO, '403 HTML es inválido');
check(HaciendaClient::interpretar('111111111', 500, '')['estado'] === HaciendaClient::NO_DISPONIBLE, '500 es no disponible');
check(HaciendaClient::interpretar('111111111', 200, '<html></html>')['estado'] === HaciendaClient::NO_DISPONIBLE, '200 sin JSON es no disponible');
check(HaciendaClient::interpretar('111111111', 200, '{"code":200}')['estado'] === HaciendaClient::NO_DISPONIBLE, '200 sin nombre es no disponible');
check(HaciendaClient::interpretar('888888888', 404, '')['contribuyente'] === null, 'Sin contribuyente si no se encontró');

fwrite(STDOUT, "hacienda_test: OK\n");
