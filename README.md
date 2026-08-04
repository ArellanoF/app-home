# Laravel con Docker

Proyecto Laravel 13 preparado para desarrollo con PHP 8.4, MariaDB y phpMyAdmin.

## Arranque

Necesitas Docker y Docker Compose. Desde la raíz del proyecto ejecuta:

```bash
docker compose up --build -d
```

La primera construcción puede tardar unos minutos. El contenedor de Laravel instala las dependencias, espera a que la base de datos esté disponible y ejecuta las migraciones automáticamente.

## Accesos

- Aplicación: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MariaDB desde el equipo anfitrión: `localhost:3306`

Credenciales de la base de datos:

| Campo | Valor |
| --- | --- |
| Servidor en Docker/phpMyAdmin | `db` |
| Base de datos | `laravel` |
| Usuario | `laravel` |
| Contraseña | `laravel` |
| Contraseña de root | `root` |

## Comandos habituales

```bash
# Ver los logs
docker compose logs -f app

# Ejecutar Artisan
docker compose exec app php artisan migrate

# Abrir una terminal en el contenedor
docker compose exec app bash

# Detener los servicios
docker compose down
```

Para eliminar también los datos persistidos de MariaDB:

```bash
docker compose down -v
```

Las credenciales incluidas son únicamente para desarrollo local. Cámbialas antes de utilizar el proyecto en cualquier entorno compartido o de producción.

## Producción económica con Coolify

El proyecto incluye un stack separado para producción:

- `Dockerfile.production`: PHP 8.4 FPM, Nginx y assets compilados.
- `docker-compose.production.yml`: aplicación, scheduler y MariaDB persistente.
- `.env.production.example`: todas las variables necesarias sin secretos reales.
- `scripts/backup-production.sh`: copia comprimida de MariaDB.

### 1. Preparar los secretos

Genera una clave de Laravel y dos contraseñas diferentes:

```bash
docker compose exec app php artisan key:generate --show
openssl rand -base64 32
openssl rand -base64 32
```

No subas un `.env` real al repositorio. En Coolify, añade las variables de
`.env.production.example` desde la sección **Environment Variables**.

Las variables obligatorias son:

```text
APP_KEY
APP_URL
DB_PASSWORD
DB_ROOT_PASSWORD
ADMIN_EMAIL
ADMIN_PASSWORD
```

`ADMIN_EMAIL` y `ADMIN_PASSWORD` crean la primera cuenta al desplegar una base
vacía. En despliegues posteriores el comando es idempotente y no duplica el
usuario. Vacía `ADMIN_PASSWORD` después del primer despliegue para que no siga
estando disponible como variable del servicio.

### 2. Crear el recurso en Coolify

1. Crea un proyecto y selecciona **Docker Compose** desde el repositorio Git.
2. Indica `docker-compose.production.yml` como archivo Compose.
3. Añade las variables de producción.
4. Asigna el dominio generado por Coolify al servicio `app`, puerto `8000`.
5. Configura `APP_URL` con ese mismo dominio HTTPS.
6. Despliega.

Coolify puede proporcionar un dominio gratuito `sslip.io` si el servidor no
tiene un dominio wildcard configurado. No publiques los puertos de MariaDB.

El contenedor web ejecuta las migraciones antes de arrancar. El contenedor
`scheduler` permanece activo y ejecuta los correos semanales de Laravel.

### 3. Verificaciones

Después del primer despliegue comprueba:

```bash
curl --fail https://TU-DOMINIO/up
docker compose -f docker-compose.production.yml ps
docker compose -f docker-compose.production.yml logs app scheduler
```

La aplicación debe responder en `/up`, permitir iniciar sesión con la cuenta
inicial y mostrar el scheduler como activo.

### 4. Copias de seguridad

Desde el servidor, exporta las variables de base de datos y ejecuta:

```bash
DB_USERNAME=vestapp \
DB_PASSWORD='tu-password' \
DB_DATABASE=vestapp \
./scripts/backup-production.sh
```

Guarda las copias fuera del VPS. El volumen de MariaDB evita perder datos al
redesplegar, pero no sustituye una copia externa.

### Prueba local del stack de producción

```bash
cp .env.production.example .env.production
# Sustituye todos los valores GENERATE_* y APP_URL.
docker compose --env-file .env.production -f docker-compose.production.yml up --build -d
```
