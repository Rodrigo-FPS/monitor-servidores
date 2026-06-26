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
| python3-venv | incluido en Python 3.11+ |
| systemd | 232+ |

---

## Instalacion del servidor central

### 1. Instalar paquetes del sistema (Debian/Ubuntu)

```bash
sudo apt-get update
sudo apt-get install -y git curl openssl \
    php8.4 php8.4-cli php8.4-fpm php8.4-pgsql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-intl php8.4-bcmath \
    composer nginx postgresql python3 python3.13-venv
```

Debian instala `php8.4` sin crear `/usr/bin/php`; Composer lo necesita:

```bash
sudo ln -sf /usr/bin/php8.4 /usr/bin/php
```

### 2. Clonar el repositorio

```bash
git clone https://github.com/Rodrigo-FPS/monitor-servidores.git /var/www/monitor
cd /var/www/monitor
```

### 3. Instalar dependencias de Laravel y ajustar permisos

Los pasos 2 y 3 se ejecutan como **root** (o con `sudo`) porque el destino es
`/var/www/`, que pertenece a root. `composer install` crea `vendor/` bajo el mismo
usuario que lo ejecuta, que en este caso sera root:

```bash
composer install --no-dev --optimize-autoloader
```

`vendor/` queda con permisos `755 root:root`. PHP-FPM corre como `www-data` y puede
leer esos archivos porque los permisos de "otros" incluyen lectura y ejecucion.

Lo que `www-data` **necesita escribir** son `storage/` y `bootstrap/cache/`
(logs, sesiones, cache de rutas). Cederles solo esos dos directorios:

```bash
sudo chown -R www-data:www-data /var/www/monitor/storage \
                                 /var/www/monitor/bootstrap/cache
```

El resto del repo (`app/`, `vendor/`, `routes/`, etc.) permanece con `root:root 755`:
www-data puede leerlo pero no modificarlo, lo que limita el impacto si PHP-FPM
queda comprometido.

> La clave de la aplicacion (`APP_KEY`) se genera mas abajo, una vez creado el
> `.env` (paso 4). En `APP_ENV=production`, `php artisan key:generate` pide
> confirmacion: usa `--force` para que no se cancele.

### 4. Crear el .env de Laravel fuera del webroot

```bash
sudo mkdir -p /etc/monitor-laravel
sudo cp .env.example /etc/monitor-laravel/.env
sudo chown www-data:www-data /etc/monitor-laravel/.env
sudo chmod 600 /etc/monitor-laravel/.env
sudo chmod 750 /etc/monitor-laravel
sudo chown root:www-data /etc/monitor-laravel
```

Generar la `APP_KEY` (escribe directamente en `/etc/monitor-laravel/.env`):

```bash
sudo -u www-data php artisan key:generate --force
```

Editar `/etc/monitor-laravel/.env` con los valores del entorno. Por ahora rellena
solo las variables de base de datos (`DB_PASSWORD` debe coincidir con la contrasena
que pongas al rol `laravel_app` en el script SQL del paso siguiente). `FASTAPI_KEY`
se rellena en el paso 8, una vez generada la clave:

```env
APP_URL=https://tu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=monitor_laravel
DB_USERNAME=laravel_app
DB_PASSWORD=contrasena-segura-laravel-app   # misma que REEMPLAZAR_CON_CONTRASENA_SEGURA_APP del paso 5

FASTAPI_URL=http://127.0.0.1:8000
FASTAPI_KEY=     # dejar vacio por ahora; se completa en el paso 8
```

### 5. Crear la base de datos de Laravel, sus tablas y el administrador

Crear la base de datos y los tres roles (como superusuario de PostgreSQL). Editar
antes las contrasenas `REEMPLAZAR_...` del script y poner en `DB_PASSWORD` del `.env`
la misma contrasena que asignes a `laravel_app`:

```bash
sudo -u postgres psql -f database/sql/usuarios_roles.sql
```

Crear las tablas con el script de esquema. Debe ejecutarse con un rol que tenga
privilegio `CREATE`: el rol de la aplicacion (`laravel_app`) sigue minimos privilegios
y NO puede crear tablas, por eso se usa el superusuario o `laravel_dba`:

```bash
sudo -u postgres psql -d monitor_laravel -f database/sql/schema.sql
```

> Alternativa solo para desarrollo: `php artisan migrate`, ejecutado con credenciales
> de un rol con privilegio `CREATE` (no `laravel_app`).

Crear el administrador con tinker (se conecta como `laravel_app`, que sí puede
insertar). Tinker usa psysh, que necesita un directorio de cache accesible por
`www-data` (Debian no lo crea automaticamente):

```bash
sudo mkdir -p /var/www/.config/psysh
sudo chown -R www-data:www-data /var/www/.config
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

### 6. Instalar dependencias de FastAPI

```bash
cd /var/www/monitor/servidor-central
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 7. Crear el .env y la base de datos de FastAPI

```bash
cp servidor-central/.env.example servidor-central/.env
```

El `.env` de FastAPI solo contiene configuracion, no credenciales. Editar
`servidor-central/.env` con los valores de temporizado (las credenciales van
en el paso siguiente via systemd):

```env
HEARTBEAT_INTERVALO_SEGUNDOS=30
HEARTBEAT_TIMEOUT_SEGUNDOS=90
VENTANA_ANTIREPLAY_SEGUNDOS=60
```

Crear la base de datos, los roles y las tablas de FastAPI. Como `fastapi_app` no tiene
privilegio `CREATE`, el esquema se aplica con el superusuario (la app ya no crea
tablas en el arranque):

```bash
# editar antes las contrasenas REEMPLAZAR_... del script de roles
sudo -u postgres psql -f servidor-central/db/usuarios_roles.sql
sudo -u postgres psql -d monitor_fastapi -f servidor-central/db/schema.sql
```

Los roles de FastAPI (`fastapi_app`, etc.) y los de Laravel (`laravel_app`, etc.)
tienen nombres distintos, por lo que ambos servicios pueden compartir el mismo
PostgreSQL sin conflicto.

### 8. Crear las credenciales de FastAPI

Ambas credenciales se inyectan via `LoadCredential` — ningun secreto toca el `.env`:

```bash
sudo mkdir -p /etc/monitor-api
sudo chmod 700 /etc/monitor-api

# DATABASE_URL — ?ssl=disable es obligatorio con ProtectHome=true (asyncpg
# intenta leer ~/.postgresql/ y systemd bloquea el home del usuario del servicio)
echo -n "postgresql+asyncpg://fastapi_app:PASSWORD@127.0.0.1:5432/monitor_fastapi?ssl=disable" \
  | sudo tee /etc/monitor-api/database_url > /dev/null

# ADMIN_API_KEY — clave aleatoria de 64 bytes en base64url
python3 -c "import secrets; print(secrets.token_urlsafe(64), end='')" \
  | sudo tee /etc/monitor-api/admin_api_key > /dev/null

sudo chmod 600 /etc/monitor-api/admin_api_key /etc/monitor-api/database_url
sudo chown root:root /etc/monitor-api/admin_api_key /etc/monitor-api/database_url
```

Sustituir `PASSWORD` en el comando de `database_url` por la contrasena real del
rol `fastapi_app` de PostgreSQL (la que pusiste en `REEMPLAZAR_CON_CONTRASENA_SEGURA_APP`
del script `servidor-central/db/usuarios_roles.sql`).

Ahora leer la `admin_api_key` generada y copiarla en el `.env` de Laravel:

```bash
sudo cat /etc/monitor-api/admin_api_key
```

Editar `/etc/monitor-laravel/.env` y pegar ese valor en `FASTAPI_KEY`:

```env
FASTAPI_KEY=<valor-copiado-de-admin_api_key>
```

### 9. Instalar el servicio systemd del FastAPI

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

### 10. Configurar nginx

Copiar la plantilla de nginx y activarla. La plantilla ya usa `server_name _;`
(acepta conexiones a cualquier IP), por lo que no necesita editarse:

```bash
sudo mkdir -p /etc/ssl/monitor
sudo cp infra/nginx/monitor.conf /etc/nginx/sites-available/monitor
sudo ln -s /etc/nginx/sites-available/monitor /etc/nginx/sites-enabled/
```

Luego ejecutar el script de configuracion de IP. Detecta automaticamente la IP
del servidor, genera el certificado SSL autofirmado, actualiza `APP_URL` en el
`.env` de Laravel y recarga nginx, todo en un solo paso:

```bash
sudo bash infra/cambiar-ip.sh
```

El navegador mostrara una advertencia de certificado la primera vez porque es
autofirmado; aceptarla una vez es suficiente. Si cambias de red, vuelve a
ejecutar el mismo script y actualiza `SERVER_URL` en cada agente cliente.

**Si tienes dominio publico (opcional):**
Obtener un certificado con Certbot antes de copiar la config de nginx:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d tu-dominio.com
```

Luego editar `infra/nginx/monitor.conf`, comentar las lineas de
`/etc/ssl/monitor/...` y descomentar las de `/etc/letsencrypt/live/...`.
Sustituir `server_name _;` por el FQDN y ejecutar los comandos de arriba.

### 11. Aplicar el indice de base de datos de FastAPI

```bash
sudo -u postgres psql -d monitor_fastapi -c \
  "CREATE INDEX IF NOT EXISTS idx_servidores_hostname ON servidores (hostname);"
```

Este indice optimiza la consulta de listado de servidores (que ordena por
`hostname`). El comando es idempotente: no falla si el indice ya existe.

---

## Instalacion del agente cliente

Ejecutar en cada servidor que se quiera monitorear como root.

### 1. Instalar paquetes del sistema (Debian/Ubuntu)

```bash
sudo apt-get update
sudo apt-get install -y python3 python3-venv
```

### 2. Instalar el agente

```bash
sudo bash cliente/instalar.sh
```

El script crea el usuario `monitor-agent`, crea un entorno virtual en
`/opt/monitor-agent/venv/`, instala las dependencias Python dentro de ese
entorno y registra el servicio systemd.

### Configurar el agente

Editar `/etc/monitor-agent/config.env` con los datos de este servidor:

```bash
sudo nano /etc/monitor-agent/config.env
```

```env
SERVER_ID=nombre-unico-del-servidor
SERVER_URL=https://ip-del-servidor-central
INTERVALO_SEGUNDOS=30
VERIFICAR_SSL=0
```

`SERVER_ID` debe ser unico en todo el sistema y usar solo letras, numeros,
guiones y guion bajo. `VERIFICAR_SSL=0` es necesario cuando el servidor
central usa un certificado autofirmado (red local / laboratorio).

### Generar las claves Ed25519 y registrar el servidor

Arrancar el agente por primera vez para que genere el par de claves Ed25519:

```bash
sudo systemctl start monitor-agent
```

Leer la clave publica generada:

```bash
sudo cat /etc/monitor-agent/public.key
```

Entrar al panel web, ir a **Agregar Servidor** e introducir el `SERVER_ID`,
hostname, IP y la clave publica completa incluyendo las lineas
`-----BEGIN PUBLIC KEY-----` y `-----END PUBLIC KEY-----`.

Una vez registrado el servidor, habilitar el servicio para que arranque
automaticamente con el sistema:

```bash
sudo systemctl enable monitor-agent
```

Verificar que los latidos llegan correctamente:

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

### Crear el archivo de contrasenas de PostgreSQL

Los cuatro roles de BD que usan los scripts (dos de respaldo, dos de restauracion) se
registran en `/root/.pgpass`. PostgreSQL ignora el archivo si los permisos no son
exactamente 600, por lo que el orden de los comandos importa:

```bash
sudo touch /root/.pgpass
sudo chmod 600 /root/.pgpass
sudo chown root:root /root/.pgpass
```

Agregar una linea por rol (sustituir cada `CONTRASENA` por el valor real):

```
127.0.0.1:5432:monitor_laravel:laravel_backup:CONTRASENA
127.0.0.1:5432:monitor_fastapi:fastapi_backup:CONTRASENA
127.0.0.1:5432:monitor_laravel:laravel_dba:CONTRASENA
127.0.0.1:5432:monitor_fastapi:fastapi_dba:CONTRASENA
```

Los roles `laravel_dba` y `fastapi_dba` solo los usa `restore.sh`; los de backup
son de solo lectura (`laravel_backup`, `fastapi_backup`).

### Configurar cron automatico

El crontab no lleva ninguna contrasena. Editar con `sudo crontab -e` y agregar:

```
# Respaldo diario de monitor_laravel a las 02:00
0 2 * * * bash /var/www/monitor/infra/backup/backup.sh >> /var/log/monitor/backup.log 2>&1

# Respaldo diario de monitor_fastapi a las 02:05
5 2 * * * DB_DATABASE=monitor_fastapi BACKUP_DB_USER=fastapi_backup bash /var/www/monitor/infra/backup/backup.sh >> /var/log/monitor/backup.log 2>&1
```

### Uso manual

```bash
# Respaldar monitor_laravel (por defecto)
sudo bash /var/www/monitor/infra/backup/backup.sh

# Respaldar monitor_fastapi
sudo DB_DATABASE=monitor_fastapi BACKUP_DB_USER=fastapi_backup bash /var/www/monitor/infra/backup/backup.sh
```

si piede contraseña usar las de usuario de respaldo de cada db

### Restauracion

```bash
sudo bash /var/www/monitor/infra/backup/restore.sh /var/backups/monitor/monitor_2026-06-25_02-00-00.sql.gz
```

El script verifica la integridad del archivo y pide confirmacion explicita
(`escribe CONFIRMAR para continuar`) antes de sobrescribir la base de datos.

Para restaurar `monitor_fastapi` en lugar de `monitor_laravel`:

```bash
sudo DB_DATABASE=monitor_fastapi DBA_DB_USER=fastapi_dba bash /var/www/monitor/infra/backup/restore.sh /var/backups/monitor/monitor_2026-06-25_02-05-00.sql.gz
```
