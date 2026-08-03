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

# Ejecutar las pruebas
docker compose exec app php artisan test

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
