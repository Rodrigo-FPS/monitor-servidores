# Monitor de Servidores

Sistema de monitoreo de servidores en red local. Cada servidor cliente ejecuta un agente que envía latidos periódicos al servidor central. El servidor central expone un panel web con el estado en tiempo real de todos los servidores registrados.

## Arquitectura

```
[Servidor cliente]                [Servidor central]
  latidos.py  ──── HTTPS/Ed25519 ──>  FastAPI (puerto 8000)
                                          |
                                        PostgreSQL
                                          |
                                      Laravel 13
                                          |
                              [Admin]  navegador web
```

- **FastAPI** (Python): recibe y valida latidos, gestiona el estado de los servidores y expone una API interna para el panel.
- **Laravel** (PHP): panel web con autenticación, muestra el estado de los servidores y permite gestionar el registro de clientes.
- **Agente cliente** (Python): corre en cada servidor monitoreado, firma y envía latidos con Ed25519.

---

## Requisitos

### Servidor central

| Componente | Version minima |
|---|---|
| PHP | 8.4 |
| Laravel | 13 |
| Composer | 2 |
| Python | 3.11 |
| pip | 23+ |
| nginx | cualquier version reciente |
| systemd | 247+ (para LoadCredential) |
| PostgreSQL | 15+ |

### Servidores cliente

| Componente | Version minima |
|---|---|
| Python | 3.11 |
| pip | 23+ |
| systemd | 232+ |

---

## Instalacion del servidor central

### 1. Clonar el repositorio

```bash
git clone https://github.com/Rodrigo-FPS/monitor-servidores.git /var/www/monitor
cd /var/www/monitor
```

### 2. Instalar dependencias de Laravel

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

### 3. Crear el .env de Laravel fuera del webroot

```bash
sudo mkdir -p /etc/monitor-laravel
sudo cp .env.example /etc/monitor-laravel/.env
sudo chown www-data:www-data /etc/monitor-laravel/.env
sudo chmod 600 /etc/monitor-laravel/.env
sudo chmod 750 /etc/monitor-laravel
sudo chown root:www-data /etc/monitor-laravel
```

Editar `/etc/monitor-laravel/.env` con los valores del entorno:

```env
APP_KEY=           # se genera con: php artisan key:generate --show
APP_URL=https://tu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=monitor_laravel
DB_USERNAME=monitor_app
DB_PASSWORD=password-seguro

FASTAPI_URL=http://127.0.0.1:8000
FASTAPI_KEY=       # misma clave que ADMIN_API_KEY del FastAPI
```

### 4. Crear la base de datos de Laravel, sus tablas y el administrador

Crear la base de datos y los tres roles (como superusuario de PostgreSQL). Editar
antes las contrasenas `REEMPLAZAR_...` del script y poner en `DB_PASSWORD` del `.env`
la misma contrasena que asignes a `monitor_app`:

```bash
sudo -u postgres psql -f database/sql/usuarios_roles.sql
```

Crear las tablas con el script de esquema. Debe ejecutarse con un rol que tenga
privilegio `CREATE`: el rol de la aplicacion (`monitor_app`) sigue minimos privilegios
y NO puede crear tablas, por eso se usa el superusuario o `monitor_dba`:

```bash
sudo -u postgres psql -d monitor_laravel -f database/sql/schema.sql
```

> Alternativa solo para desarrollo: `php artisan migrate`, ejecutado con credenciales
> de un rol con privilegio `CREATE` (no `monitor_app`).

Crear el administrador con tinker (se conecta como `monitor_app`, que sí puede
insertar):

```bash
sudo -u www-data php artisan tinker
```

Dentro de tinker, crear el administrador. **El rol debe fijarse explicitamente**:
el campo `rol` no es asignable en masa y por defecto vale `observador` (minimo
privilegio), por lo que crear la cuenta SIN fijar el rol da una cuenta de solo
lectura. Para crear un administrador:

```php
$u = new App\Models\Usuario();
$u->username = 'admin';
$u->password = bcrypt('contrasena-segura');
$u->rol      = 'admin';
$u->save();
exit
```

El modelo `Usuario` (tabla `usuarios`) solo tiene los campos `username`, `password`
y `rol` (el hash se almacena con bcrypt). No uses `name` ni `email`: no existen en la
tabla.

Ver la seccion **Roles y gestion de usuarios** mas abajo para crear cuentas de solo
lectura y entender el modelo de roles.

### 5. Instalar dependencias de FastAPI

```bash
cd /var/www/monitor/servidor-central
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 6. Crear el .env y la base de datos de FastAPI

```bash
cp servidor-central/.env.example servidor-central/.env
```

Editar `servidor-central/.env`. `ADMIN_API_KEY` se gestiona via systemd, pero
`DATABASE_URL` es obligatoria (la app no arranca sin ella):

```env
DATABASE_URL=postgresql+asyncpg://monitor_app:password-seguro@127.0.0.1:5432/monitor_fastapi
HEARTBEAT_INTERVALO_SEGUNDOS=30
HEARTBEAT_TIMEOUT_SEGUNDOS=90
VENTANA_ANTIREPLAY_SEGUNDOS=60
```

Crear la base de datos, los roles y las tablas de FastAPI. Como `monitor_app` no tiene
privilegio `CREATE`, el esquema se aplica con el superusuario (la app ya no crea
tablas en el arranque):

```bash
# editar antes las contrasenas REEMPLAZAR_... del script de roles
sudo -u postgres psql -f servidor-central/db/usuarios_roles.sql
sudo -u postgres psql -d monitor_fastapi -f servidor-central/db/schema.sql
```

> Nota: si Laravel y FastAPI comparten el mismo PostgreSQL, los dos scripts
> `usuarios_roles.sql` crean roles con los mismos nombres (`monitor_app`, etc.).
> Ejecuta el segundo sin recrear los roles ya existentes, o usa nombres de rol
> distintos por base de datos.

### 7. Crear la credencial de API del FastAPI

Generar una clave aleatoria y guardarla como credencial de systemd:

```bash
python3 -c "import secrets; print(secrets.token_urlsafe(64))" | sudo tee /etc/monitor-api/admin_api_key
sudo chmod 600 /etc/monitor-api/admin_api_key
sudo chown root:root /etc/monitor-api/admin_api_key
sudo chmod 700 /etc/monitor-api
```

Copiar el mismo valor en `FASTAPI_KEY` dentro de `/etc/monitor-laravel/.env`.

### 8. Instalar el servicio systemd del FastAPI

Crear el usuario de sistema dedicado (sin login) con el que corre el backend:

```bash
sudo useradd --system --no-create-home --shell /sbin/nologin monitor-api
```

Ajustar en `servidor-central/monitor-fastapi.service` el `WorkingDirectory` y la
ruta de `ExecStart` a la ubicacion real del despliegue (por defecto
`/var/www/monitor/servidor-central`). El servicio ya enlaza uvicorn solo a
`127.0.0.1` (backend interno) y viene confinado con systemd. Luego:

```bash
sudo cp servidor-central/monitor-fastapi.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now monitor-fastapi
```

Verificar:

```bash
sudo systemctl status monitor-fastapi
```

### 9. Configurar nginx

Copiar y adaptar la plantilla incluida:

```bash
sudo cp infra/nginx/monitor.conf /etc/nginx/sites-available/monitor
sudo ln -s /etc/nginx/sites-available/monitor /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Editar el archivo para reemplazar `TU_DOMINIO` y las rutas de certificado SSL.

---

## Instalacion del agente cliente

Ejecutar en cada servidor que se quiera monitorear como root:

```bash
sudo bash cliente/instalar.sh
```

El script crea el usuario `monitor-agent`, instala las dependencias Python y registra el servicio systemd.

### Configurar el agente

```bash
sudo nano /etc/monitor-agent/config.env
```

```env
SERVER_ID=nombre-unico-del-servidor
SERVER_URL=https://tu-dominio.com
INTERVALO_SEGUNDOS=30
```

`SERVER_ID` debe ser unico en todo el sistema y usar solo letras, numeros, guiones y guion bajo.

### Generar las claves Ed25519 y registrar el servidor

Arrancar el agente una sola vez para que genere el par de claves:

```bash
sudo systemctl start monitor-agent
```

Leer la clave publica generada:

```bash
sudo cat /etc/monitor-agent/public.key
```

Entrar al panel web, ir a **Agregar Servidor** e introducir el `SERVER_ID`, hostname, IP y la clave publica completa incluyendo las lineas `-----BEGIN PUBLIC KEY-----` y `-----END PUBLIC KEY-----`.

Una vez registrado el servidor, el agente comenzara a enviar latidos. Verificar:

```bash
sudo journalctl -u monitor-agent -f
```

---

## Autenticacion de los agentes

Cada agente se autentica con firma asimetrica Ed25519:

1. El agente genera un par de claves en el primer arranque. La clave privada queda en `/etc/monitor-agent/private.key` con permisos 400.
2. El administrador registra la clave publica en el servidor central a traves del panel web.
3. En cada latido el agente firma el mensaje `server_id:timestamp_iso` con su clave privada y lo envia en el header `Authorization: Ed25519 <firma-base64>`.
4. FastAPI verifica la firma contra la clave publica almacenada y rechaza peticiones con timestamp fuera de la ventana configurada en `VENTANA_ANTIREPLAY_SEGUNDOS` (proteccion antireplay).
5. Ademas de la firma, FastAPI verifica que la IP del cliente coincida con la IP registrada para ese `server_id`.

La clave privada nunca se transmite ni se almacena fuera del servidor cliente.

---

## Roles y gestion de usuarios

Las cuentas viven en la tabla `usuarios` (modelo `App\Models\Usuario`). Cada cuenta
tiene un `rol` de aplicacion (distinto de los roles de PostgreSQL):

| Rol | Puede ver el estado | Puede agregar/editar/eliminar servidores |
|---|---|---|
| `admin`      | Si | Si |
| `observador` | Si | No (solo lectura) |

La autorizacion se aplica en el servidor (middleware `rol.admin` + revalidacion en el
controlador, ambos fallan cerrado). Ocultar botones en la interfaz es solo cosmetico:
un `observador` que fuerce una peticion de escritura recibe `403`.

### No hay registro de usuarios (limitacion del proyecto)

El sistema **no incluye registro de usuarios**: no existe ninguna pagina ni endpoint
para crear cuentas desde la web. Las cuentas se dan de alta manualmente en el servidor
por un administrador con acceso por consola.

Justificacion: un panel de monitoreo lo usa un grupo pequeno y fijo de operadores de
confianza. Un registro publico anadiria una superficie de ataque grande e innecesaria
(alta automatizada de cuentas, enumeracion de usuarios, y flujos de verificacion por
correo o de recuperacion de contrasena, que son a su vez vectores de ataque). Al no
existir registro, el sistema no tiene ningun flujo de creacion de cuentas accesible sin
autenticacion, y el alta queda restringida a quien ya tiene acceso al servidor. La
contrapartida (limitacion) es que **agregar o quitar usuarios requiere acceso por linea
de comandos al servidor**, no se hace desde la interfaz. Es aceptable para el alcance del
proyecto (pocos operadores) y se documentara con mas detalle en el apartado de
restricciones del documento.

### Como dar de alta usuarios

En el servidor, con `sudo -u www-data php artisan tinker`:

```php
// Administrador (control total) — el rol debe fijarse explicitamente
$a = new App\Models\Usuario();
$a->username = 'admin';
$a->password = bcrypt('contrasena-larga-y-unica');
$a->rol      = 'admin';
$a->save();

// Observador (solo lectura) — el rol por defecto ya es 'observador'
$o = new App\Models\Usuario();
$o->username = 'lector';
$o->password = bcrypt('otra-contrasena-larga');
$o->save();
```

El campo `rol` no es asignable en masa y cualquier valor invalido se normaliza a
`observador` (minimo privilegio por defecto).

## Estructura del repositorio

```
/
|-- bootstrap/app.php          Laravel: configura el path del .env fuera del webroot
|-- app/                       Controladores, middlewares y modelos de Laravel
|-- resources/views/           Vistas Blade del panel web
|-- routes/                    Rutas web de Laravel
|-- public/                    Webroot: assets JS/CSS/fuentes (servidos localmente)
|-- servidor-central/          Backend FastAPI
|   |-- main.py                Punto de entrada de la aplicacion
|   |-- config.py              Carga de configuracion y credenciales
|   |-- api/                   Endpoints: heartbeat, shutdown, servidores
|   |-- auth/                  Validacion de firma Ed25519
|   |-- db/                    Modelos SQLAlchemy y helpers de base de datos
|   |-- middleware/             Security headers de FastAPI
|   |-- requirements.txt       Dependencias Python
|   |-- monitor-fastapi.service Servicio systemd con LoadCredential
|-- cliente/                   Agente para servidores monitoreados
|   |-- latidos.py             Demonio de heartbeat
|   |-- instalar.sh            Script de instalacion
|   |-- config.env.example     Plantilla de configuracion
|   |-- monitor-agent.service  Servicio systemd del agente
|-- infra/
|   |-- nginx/monitor.conf     Configuracion nginx de referencia
|   |-- backup/                Scripts de backup y restauracion
|-- database/migrations/       Migraciones de Laravel
```

---

## Seguridad

- Las sesiones de Laravel se cifran (`SESSION_ENCRYPT=true`) y el `.env` se almacena fuera del webroot en `/etc/monitor-laravel/` con permisos 600.
- `ADMIN_API_KEY` del FastAPI no existe en ningun archivo `.env` en disco; se inyecta en tiempo de ejecucion via `systemd LoadCredential` desde `/etc/monitor-api/admin_api_key` (root:root, 600).
- La CSP del panel web bloquea recursos externos (`script-src 'self'`). Bootstrap, jQuery y Font Awesome se sirven localmente desde `/public/`.
- El login tiene proteccion contra fuerza bruta: bloqueo temporal tras `LOGIN_MAX_INTENTOS` intentos fallidos en `LOGIN_VENTANA_MINUTOS` minutos.
- El agente cliente corre bajo el usuario sin privilegios `monitor-agent` con el servicio systemd confinado (`NoNewPrivileges`, `ProtectSystem=strict`, `MemoryDenyWriteExecute`).
- Registro de eventos: FastAPI escribe en `/var/log/monitor/` (`seguridad.log` con los rechazos de latidos/apagados y fallos de API key; `estados.log` con los cambios de estado). Laravel escribe en `storage/logs/` (`auditoria.log` con login y altas/ediciones/borrados de servidores; `seguridad.log` con logins fallidos, bloqueos por fuerza bruta y deteccion de secuestro de sesion). El historial de estados queda en logs, no en la base de datos.
- Endurecimiento de produccion: `/docs`, `/redoc` y `/openapi.json` deshabilitados; nginx con rate-limit en `/login` y limite de conexiones por IP; cabeceras que revelan versiones ocultas (`server_tokens off`, `--no-server-header`, `X-Powered-By`).
