#!/bin/sh
set -eu

compose_file="${COMPOSE_FILE:-docker-compose.production.yml}"
backup_dir="${BACKUP_DIR:-backups}"
timestamp="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$backup_dir"
docker compose -f "$compose_file" exec -T db \
    mariadb-dump -u"${DB_USERNAME:-vestapp}" -p"${DB_PASSWORD:?DB_PASSWORD is required}" \
    --single-transaction --routines --triggers "${DB_DATABASE:-vestapp}" \
    | gzip > "$backup_dir/vestapp-$timestamp.sql.gz"

echo "Backup created: $backup_dir/vestapp-$timestamp.sql.gz"
