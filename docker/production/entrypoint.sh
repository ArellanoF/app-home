#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required" >&2
    exit 1
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force

    if [ -n "${ADMIN_EMAIL:-}" ]; then
        php artisan app:bootstrap
    fi
fi

php artisan config:cache
php artisan view:cache

exec "$@"
