# Backend — API de Cuentas por Pagar

API JSON en PHP 8.4+ para el sistema de cuentas por pagar. Usa los procedimientos y vistas del OLTP y las vistas de reportes del DW.

## Requisitos

- PHP 8.4 o superior.
- SQL Server con la base `CuentasPorPagarDB` y, para reportes, `CuentasPorPagarDW`.
- Extensión `pdo_sqlsrv` (producción) o `pdo_odbc` (desarrollo local en Windows).
- Haber ejecutado los scripts de la rama `feature/base-datos` en este orden:
  1. `sql/oltp/00_base.sql`
  2. `sql/oltp/01_correcciones.sql`
  3. `sql/oltp/02_lectura.sql`
  4. `sql/dw/01_schema_dw.sql` a `sql/dw/05_validacion.sql`

## Configuración

```powershell
Copy-Item .env.example .env
```

Editar `.env`:

```ini
DB_DRIVER=odbc
DB_HOST=.\SQLEXPRESS
DB_NAME=CuentasPorPagarDB
DB_TRUSTED=1
DB_ENCRYPT=1
DB_TRUST_SERVER_CERTIFICATE=1
```

Para SQL Server con autenticación SQL:

```ini
DB_TRUSTED=0
DB_USER=usuario
DB_PASSWORD=contraseña
```

Para producción con `pdo_sqlsrv`:

```ini
DB_DRIVER=sqlsrv
```

## Ejecutar en desarrollo

Desde la raíz del repositorio:

```powershell
php -d extension=php_odbc.dll -d extension=php_pdo_odbc.dll -S localhost:8000 backend/public/index.php
```

Sin las extensiones ODBC cargadas, el servidor arranca pero la API responde error de conexión. En un PHP con `pdo_sqlsrv` habilitado no se necesitan los `-d`.

Comprobar:

```powershell
Invoke-RestMethod http://localhost:8000/api/health
```

## Endpoints iniciales

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/health` | Estado del servicio y de la conexión. |
| POST | `/api/auth/login` | Inicia sesión; devuelve usuario y token CSRF. |
| POST | `/api/auth/logout` | Cierra la sesión. |
| GET | `/api/auth/me` | Usuario autenticado y token CSRF. |
| GET | `/api/proveedores` | Lista paginada de proveedores. |
| POST | `/api/proveedores` | Crea un proveedor. |
| GET | `/api/proveedores/{id}` | Detalle de proveedor. |
| PUT | `/api/proveedores/{id}` | Actualiza un proveedor. |
| GET | `/api/cuentas` | Lista paginada de facturas. |
| POST | `/api/cuentas` | Registra una factura con detalle JSON. |
| GET | `/api/cuentas/{id}` | Detalle de factura y líneas. |
| POST | `/api/cuentas/{id}/anular` | Anula una factura sin pagos aprobados. |
| GET | `/api/movimientos` | Lista paginada de movimientos. |
| POST | `/api/pagos` | Registra un pago y calcula el descuento. |
| POST | `/api/pagos/{id}/aprobar` | Aprueba un pago o descuento. |
| POST | `/api/pagos/{id}/anular` | Anula pago y descuento en una transacción. |
| POST | `/api/movimientos/{id}/anular` | Anula un movimiento sin descuento. |
| GET | `/api/catalogos` | Bancos, métodos, tipos de pago, estados y roles. |
| GET | `/api/dashboard` | Resumen de cuentas abiertas, vencidas y pagos del mes. |
| GET | `/api/reportes/aging` | Antigüedad actual desde el DW. |
| GET | `/api/reportes/pagos-mes` | Pagos aprobados por mes desde el DW. |
| GET | `/api/reportes/cumplimiento` | Cumplimiento de plazos desde el DW. |
| GET | `/api/reportes/saldos-proveedor` | Saldos abiertos por proveedor desde el DW. |

## Seguridad

- Sesiones PHP con cookie `HttpOnly` y `SameSite=Lax`.
- Token CSRF obligatorio en `POST`, `PUT`, `PATCH` y `DELETE`, excepto el login.
- El token se obtiene en la respuesta del login o en `GET /api/auth/me` y se envía en el encabezado `X-CSRF-Token`.
- Las consultas usan marcadores posicionales `?`; no se concatenan valores del usuario.
- Los errores de SQL Server se registran en `storage/logs/app.log`; en producción no se devuelve el detalle interno.

## Estructura

```text
backend/
  public/index.php          Front controller
  src/
    Config.php              Carga de .env
    Database.php            Conexión PDO y helpers
    Http/                   Request, Response, Router, HttpException
    Security/               Auth y CSRF
    Repositories/           Acceso a datos
    Controllers/            Endpoints
    Support/                Validación, errores, logger
  tests/                    Pruebas sin framework
  bootstrap.php
  autoload.php
```

## Pruebas

```powershell
php backend/tests/router_test.php
php backend/tests/validator_test.php
```
