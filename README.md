# Sistema de Gestión de Cuentas por Pagar

Sistema académico de cuentas por pagar con base de datos OLTP, Data Warehouse, API PHP y una interfaz web PHP + Bulma + HTMX.

La rama `develop` integra las tres partes del proyecto en un solo checkout:

- `sql/`: base OLTP, Data Warehouse, ETL y reportes.
- `backend/`: API JSON, autenticación por sesión, CSRF y acceso a SQL Server.
- `frontend/`: interfaz web que consume la API desde el servidor con cURL.

> `main` conserva únicamente la documentación general. Para probar el sistema use `develop`.

## Arquitectura

```text
Navegador
   │  HTML + HTMX
   ▼
Frontend PHP · http://localhost:8090
   │  JSON + cookie de sesión (cURL)
   ▼
API PHP · http://localhost:8000
   │  PDO ODBC
   ▼
SQL Server · CuentasPorPagarDB + CuentasPorPagarDW
```

El navegador solo se comunica con el frontend. La sesión del API se conserva en el servidor del frontend, en un archivo privado dentro de `frontend/storage/cookies/`.

## Requisitos

- PHP 8.4 o superior.
  - API: extensiones `pdo_odbc` y `odbc`.
  - Frontend: extensión `curl`.
- SQL Server 2019 o superior (Express es suficiente).
- Microsoft ODBC Driver 18 for SQL Server.
- `sqlcmd` o SSMS para ejecutar los scripts.
- Git.

En Windows, las extensiones se pueden cargar al iniciar PHP con `-d extension=...`; en Linux/macOS normalmente se habilitan en `php.ini`.

## 1. Clonar e ingresar a la rama integrada

```powershell
git clone -b develop https://github.com/PabloD6003/Sistema_Gestion_Cuentas_por_Pagar.git
cd Sistema_Gestion_Cuentas_por_Pagar
```

Si el repositorio ya estaba clonado:

```powershell
git fetch origin
git switch develop
git pull
```

## 2. Crear y cargar la base de datos

Ejecute los scripts en este orden desde la raíz del repositorio. Ajuste `-S ".\SQLEXPRESS"` si su instancia tiene otro nombre.

> `sql/oltp/00_base.sql` recrea los objetos de la base. Úselo solo en un entorno de desarrollo o en una base que pueda reiniciarse.

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\00_base.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\01_correcciones.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\02_lectura.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\oltp\03_usuarios_demo.sql
```

Luego cargue el Data Warehouse:

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\01_schema_dw.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\02_dim_tiempo.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\03_etl.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -Q "USE CuentasPorPagarDW; EXEC etl.sp_ejecutar_todo;"
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\04_views.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -i sql\dw\05_validacion.sql
```

El script `03_etl.sql` solo crea los procedimientos. La carga ocurre al ejecutar `etl.sp_ejecutar_todo`. Los reportes del sistema no muestran datos hasta que se ejecuta esa carga.

Al final, `05_validacion.sql` debe mostrar `OK` en todos los controles. El ETL es idempotente y puede volver a ejecutarse.

Si usa autenticación SQL en lugar de autenticación integrada de Windows, reemplace `-E` por:

```powershell
-U sa -P "SuContraseña"
```

## 3. Configurar los archivos .env

```powershell
Copy-Item backend\.env.example backend\.env
Copy-Item frontend\.env.example frontend\.env
```

Valores importantes de `backend/.env`:

```ini
DB_HOST=.\SQLEXPRESS
DB_NAME=CuentasPorPagarDB
DB_TRUSTED=1
DW_NAME=CuentasPorPagarDW
```

Si su instancia es distinta, cambie `DB_HOST`. Para autenticación SQL:

```ini
DB_TRUSTED=0
DB_USER=sa
DB_PASSWORD=SuContraseña
```

`frontend/.env` ya apunta a `http://localhost:8000` mediante `API_BASE_URL`. Los archivos `.env` no se versionan.

## 4. Ejecutar la API y la interfaz

Abra dos terminales en la raíz del repositorio.

**Terminal 1 — API:**

```powershell
php -d extension=php_odbc.dll -d extension=php_pdo_odbc.dll -S localhost:8000 -t backend/public backend/public/index.php
```

**Terminal 2 — Interfaz:**

```powershell
php -d extension=php_curl.dll -S localhost:8090 -t frontend/public frontend/public/index.php
```

Si las extensiones ya están habilitadas en `php.ini`, omita los `-d extension=...`.

Abra <http://localhost:8090>.

> En este equipo el puerto `8080` está reservado por Windows; por eso la interfaz usa `8090`. Si ese puerto está ocupado, cambie el número en el comando y abra la URL correspondiente.

Compruebe la API:

```powershell
Invoke-RestMethod http://localhost:8000/api/health
```

Debe devolver `status = ok` y `database = true`.

## Usuarios de prueba

El script `03_usuarios_demo.sql` crea estas cuentas:

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `Admin1234` | Administrador |
| `tesorero` | `Tesorero123` | Tesorero |
| `contador` | `Contador123` | Contador |

Administrador y Tesorero pueden aprobar pagos; el Contador puede registrar facturas y pagos, pero no aprobarlos. Cambie estas contraseñas si va a publicar el sistema fuera de un entorno de prueba.

## Pruebas

```powershell
php backend/tests/router_test.php
php backend/tests/validator_test.php
php frontend/tests/router_test.php
php frontend/tests/format_test.php
```

También puede validar la sintaxis de todos los archivos PHP:

```powershell
Get-ChildItem backend,frontend -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Estructura

```text
backend/                 API JSON
  public/index.php       Front controller
  src/                   Controladores, repositorios, seguridad
  tests/                 Pruebas sin framework
frontend/                Interfaz PHP + Bulma + HTMX
  public/                Front controller y assets locales
  src/                   Cliente de API, controladores y helpers
  templates/             Vistas
  tests/                 Pruebas de router y formatos
sql/
  oltp/                  Esquema, correcciones, vistas y usuarios demo
  dw/                    Esquema dimensional, ETL, vistas y validación
docs/                    Documentación y diagramas
```

## Alcance y limitaciones

- Moneda única (CRC); no hay multimoneda.
- El Data Warehouse se alimenta con un ETL batch; los reportes muestran la última carga.
- La conciliación de pagos se muestra como estado informativo; no se captura desde la interfaz.
- Las tablas usan paginación del API, pero la interfaz no incluye controles de páginas.
- El proyecto está pensado para ejecución local y académica; no incluye despliegue en producción, HTTPS ni un servidor web empresarial.

## Problemas frecuentes

- **`could not find driver` o error de conexión:** confirme que `pdo_odbc` y `odbc` están disponibles (`php -m`) y que el ODBC Driver 18 está instalado.
- **Error de cURL en la interfaz:** habilite `curl` o use `-d extension=php_curl.dll`.
- **No puedo iniciar sesión:** ejecute `sql/oltp/03_usuarios_demo.sql` y confirme que los usuarios existen.
- **Los reportes están vacíos:** ejecute `EXEC etl.sp_ejecutar_todo` en `CuentasPorPagarDW`.
- **No conecta a SQL Server:** revise `DB_HOST` y el nombre real de la instancia; si usa autenticación SQL, revise `DB_TRUSTED`, `DB_USER` y `DB_PASSWORD`.
- **El puerto está ocupado:** cambie `8000` o `8090` en los comandos y, para el API, actualice `API_BASE_URL` en `frontend/.env`.
