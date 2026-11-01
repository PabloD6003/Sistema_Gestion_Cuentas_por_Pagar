# Sistema de Gestión de Cuentas por Pagar

Sistema académico de cuentas por pagar con base de datos OLTP, Data Warehouse, API PHP y una interfaz web PHP + Bulma + HTMX.

La rama `development` integra las tres partes del proyecto en un solo checkout:

- `sql/`: base OLTP, Data Warehouse, ETL y reportes.
- `backend/`: API JSON, autenticación por sesión, CSRF y acceso a SQL Server.
- `frontend/`: interfaz web que consume la API desde el servidor con cURL.

> `main` conserva únicamente la documentación general. Para probar el sistema use `development`.

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
git clone -b development https://github.com/PabloD6003/Sistema_Gestion_Cuentas_por_Pagar.git
cd Sistema_Gestion_Cuentas_por_Pagar
```

Si el repositorio ya estaba clonado:

```powershell
git fetch origin
git switch development
git pull
```

## 2. Crear y cargar la base de datos

Ejecute los scripts en este orden desde la raíz del repositorio. Ajuste `-S ".\SQLEXPRESS"` si su instancia tiene otro nombre.

> `sql/oltp/00_base.sql` recrea los objetos de la base. Úselo solo en un entorno de desarrollo o en una base que pueda reiniciarse.

> `-f 65001` hace que `sqlcmd` lea los scripts como UTF-8. Sin esa opción las tildes de los textos (por ejemplo `'Nota de crédito'`) se guardan corruptas y el ETL deja de reconocer esos movimientos.

> Si ya tiene la base creada, `01_correcciones.sql` y los scripts del DW se pueden volver a ejecutar sin perder datos para aplicar las correcciones más recientes.

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\oltp\00_base.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\oltp\01_correcciones.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\oltp\02_lectura.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\oltp\03_usuarios_demo.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\oltp\04_consultas.sql
```

Luego cargue el Data Warehouse:

```powershell
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\01_schema_dw.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\02_dim_tiempo.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\03_etl.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -Q "USE CuentasPorPagarDW; EXEC etl.sp_ejecutar_todo;"
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\04_views.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\05_validacion.sql
sqlcmd -S ".\SQLEXPRESS" -E -C -I -b -f 65001 -i sql\dw\06_consultas.sql
```

La API no ejecuta `SELECT` directos: todas las lecturas pasan por los procedimientos de `sql/oltp/04_consultas.sql` y `sql/dw/06_consultas.sql`, y todas las escrituras por los procedimientos de `00_base.sql` y `01_correcciones.sql`. Si ya tenía la base creada, basta con ejecutar esos dos scripts.

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

## Módulos de la interfaz

| Módulo | Roles | Funciones |
|---|---|---|
| Proveedores | Todos | Catálogo, expediente con historial de pagos, calificación promedio y evaluaciones de la relación comercial. |
| Cuentas por pagar | Todos (anular: Tesorero y Administrador) | Registro de facturas, modificación mientras no tengan pagos vigentes, pagos y anulaciones. |
| Pagos | Todos (aprobar y anular: Tesorero y Administrador) | Aprobación de pagos; el descuento por pronto pago se aprueba junto con su pago. |
| Tesorería | Tesorero y Administrador | Lotes de transferencia por banco (generar, enviar, procesar, rechazar) y conciliación del extracto bancario. |
| Reportes | Todos | Antigüedad, pagos por mes, cumplimiento y saldos (Data Warehouse) y flujo de caja proyectado (OLTP, al día). |
| Respaldos | Administrador | Respaldo completo, diferencial y de log, con historial. |

Las cuentas cuyo vencimiento pasó cambian a **Vencida** automáticamente al consultar el panel o las cuentas por pagar, y también al ejecutar el ETL.

### Conciliación bancaria

En **Tesorería**, elija el banco y pegue el extracto, una línea por movimiento con fecha, referencia y monto, separados por punto y coma, coma o tabulador (sirve copiar desde Excel):

```text
Fecha;Referencia;Monto
2026-10-03;TRF-1004;1,220,400.00
16/10/2026;TRF-7004;355950
```

Cada línea se concilia con un pago aprobado del mismo banco que tenga igual referencia y monto, y cuya fecha difiera como máximo en la tolerancia indicada (3 días por defecto). El resultado muestra qué líneas se conciliaron y cuáles no tienen coincidencia.

## Respaldos

El Administrador ve en el menú la opción **Respaldos**, con tres botones:

| Botón | Qué hace |
|---|---|
| Respaldo completo | Copia toda la base `CuentasPorPagarDB`. Es el punto de partida de los demás. |
| Respaldo diferencial | Copia solo los cambios desde el último respaldo completo. |
| Respaldo de log | Respaldo incremental del registro de transacciones (la base usa el modelo de recuperación FULL). |

Cada respaldo se verifica con `RESTORE VERIFYONLY`, queda en la bitácora de auditoría y aparece en el historial de la misma pantalla. Los archivos se guardan en la carpeta de respaldos de la instancia de SQL Server; para usar otra carpeta del servidor, indíquela en `BACKUP_PATH` dentro de `backend/.env`.

La cuenta con la que la API se conecta a SQL Server necesita permiso para respaldar (`db_backupoperator`) y para verificar el archivo (`CREATE DATABASE`). Con autenticación de Windows en un equipo de desarrollo normalmente ya los tiene.

## Pruebas

```powershell
php backend/tests/router_test.php
php backend/tests/validator_test.php
php backend/tests/sql_error_test.php
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
- El Data Warehouse se alimenta con un ETL batch; los reportes del DW muestran la última carga. El flujo de caja proyectado se calcula al momento desde el OLTP.
- La conciliación bancaria se hace pegando el extracto en la pantalla de Tesorería; no hay conexión directa con los bancos.
- Los comprobantes de pago quedan en la cola de notificaciones (`CP18_notificaciones`); el envío del correo no está implementado.
- Las tablas usan paginación del API, pero la interfaz no incluye controles de páginas.
- El proyecto está pensado para ejecución local y académica; no incluye despliegue en producción, HTTPS ni un servidor web empresarial.

## Problemas frecuentes

- **`could not find driver` o error de conexión:** confirme que `pdo_odbc` y `odbc` están disponibles (`php -m`) y que el ODBC Driver 18 está instalado.
- **Error de cURL en la interfaz:** habilite `curl` o use `-d extension=php_curl.dll`.
- **No puedo iniciar sesión:** ejecute `sql/oltp/03_usuarios_demo.sql` y confirme que los usuarios existen.
- **Los reportes están vacíos:** ejecute `EXEC etl.sp_ejecutar_todo` en `CuentasPorPagarDW`.
- **No conecta a SQL Server:** revise `DB_HOST` y el nombre real de la instancia; si usa autenticación SQL, revise `DB_TRUSTED`, `DB_USER` y `DB_PASSWORD`.
- **El puerto está ocupado:** cambie `8000` o `8090` en los comandos y, para el API, actualice `API_BASE_URL` en `frontend/.env`.
