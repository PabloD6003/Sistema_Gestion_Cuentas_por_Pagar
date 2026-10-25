# Interfaz web · Cuentas por Pagar

Aplicación PHP con Bulma y HTMX que consume la API JSON del backend. El navegador habla únicamente con este frontend; el frontend llama a la API desde el servidor con cURL y conserva la cookie de sesión del API en un archivo privado.

## Arquitectura

```text
Navegador
   │  HTML + HTMX
   ▼
Frontend PHP (puerto 8090)
   │  JSON + cookie de sesión (cURL)
   ▼
API PHP (puerto 8000)
   │  PDO ODBC / sqlsrv
   ▼
SQL Server · CuentasPorPagarDB + CuentasPorPagarDW
```

- La sesión del navegador es propia del frontend (`cxp_ui_session`).
- El frontend guarda la cookie de sesión del API en `frontend/storage/cookies/`.
- Los formularios usan un token CSRF del frontend; el frontend usa el token CSRF del API en cada operación de escritura.
- HTMX actualiza listados, filtros y pestañas de reportes sin recargar la página.

## Requisitos

- PHP 8.4 o superior con las extensiones `curl`, `session`, `json` e `iconv`.
- El backend de `feature/backend` ejecutándose y accesible desde el servidor del frontend.
- SQL Server con las bases `CuentasPorPagarDB` y `CuentasPorPagarDW`.
- No requiere Composer, Node.js ni compilación de assets.

> En el PHP local de Windows, `curl` se carga con `-d extension=php_curl.dll`. Verifique con `php -m` y ajuste el comando si su `php.ini` ya lo habilita.

## Configuración local

Desde la raíz del repositorio:

```powershell
Copy-Item frontend\.env.example frontend\.env
```

Edite `frontend/.env` si la API no está en `http://localhost:8000`:

```dotenv
API_BASE_URL=http://localhost:8000
CURRENCY_SYMBOL=₡
APP_DEBUG=1
```

## Ejecución

Terminal 1, backend (rama `feature/backend` o `main` después del merge):

```powershell
php -d extension=php_odbc.dll -d extension=php_pdo_odbc.dll -S localhost:8000 backend/public/index.php
```

Terminal 2, frontend:

```powershell
php -d extension=php_curl.dll -S localhost:8090 -t frontend/public frontend/public/index.php
```

Abra <http://localhost:8090>.

> En este equipo el puerto `8080` está reservado por Windows; por eso la interfaz usa `8090`. Si `8080` está libre, puede cambiarlo en el comando y en la URL.

### Usuarios de prueba

| Usuario | Contraseña | Rol | Puede aprobar |
|---|---|---|---|
| `admin` | `Admin1234` | Administrador | Sí |
| `tesorero` | `Tesorero123` | Tesorero | Sí |
| `contador` | `Contador123` | Contador | No |

Estos usuarios se crean con los scripts de prueba de la base de datos. En un ambiente real deben crearse con `sp_usuario_crear` y contraseñas propias.

## Pantallas

- **Panel**: cuentas abiertas, saldo pendiente, vencidas, pagos del mes, antigüedad del DW y estado del ETL.
- **Proveedores**: búsqueda y filtro con HTMX; alta de proveedores con condiciones de crédito y cuenta bancaria.
- **Cuentas por pagar**: filtros con HTMX, alta de facturas con detalle dinámico, vista de factura con movimientos y formulario de pago.
- **Pagos**: listado de movimientos, filtros, aprobación y anulación con motivo.
- **Reportes**: pestañas HTMX para antigüedad, pagos por mes, cumplimiento, saldos por proveedor y estado del ETL.

## Flujo recomendado de demostración

1. Ingresar como `contador`.
2. Crear un proveedor y una factura.
3. Registrar un pago. Si la factura tiene descuento por pronto pago y está dentro del plazo, el sistema crea también el movimiento de descuento.
4. Cerrar sesión e ingresar como `tesorero` o `admin`.
5. Aprobar el pago y, si existe, el descuento. La factura queda pagada.
6. Anular el pago desde la factura o desde la lista de pagos; el saldo se restaura.
7. Ejecutar el ETL del DW si se desea ver los datos en los reportes.

## Seguridad y despliegue

- Sirva `frontend/public` como raíz del sitio; `frontend/storage` debe quedar fuera del directorio público.
- Mantenga `frontend/.env` fuera de Git (ya está ignorado).
- Use HTTPS en producción; ajuste `secure` en la cookie de sesión si corresponde.
- El API puede alojarse en otro host interno; el navegador no necesita acceder a él.
- Para Apache, `frontend/public/.htaccess` reescribe las rutas a `index.php`. En Nginx use `try_files $uri $uri/ /index.php?$query_string`.
- No exponga `frontend/storage` ni los archivos de cookies del API.

## Pruebas

```powershell
php frontend/tests/router_test.php
php frontend/tests/format_test.php
```

Las pruebas no requieren la API ni la base de datos.

## Limitaciones actuales

- Los listados muestran los primeros 50 registros; la paginación visual queda como siguiente iteración.
- La edición de proveedores y el cambio de contraseña aún no tienen pantalla propia.
- Los reportes son de solo lectura y dependen de que el ETL diario se haya ejecutado.
- No se incluye administración de usuarios, roles ni programaciones en la interfaz.
