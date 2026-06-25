#!/usr/bin/env bash

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
# Por defecto respalda la BD de Laravel. Para la de FastAPI exporta
# DB_DATABASE=monitor_fastapi y BACKUP_DB_USER=fastapi_backup.
DB_DATABASE="${DB_DATABASE:-monitor_laravel}"
DB_USER="${BACKUP_DB_USER:-laravel_backup}"
# La contrasena se lee desde /root/.pgpass (600 root:root).
# pg_dump nunca recibe ni almacena la contrasena como argumento ni variable visible.
export PGPASSFILE="${PGPASSFILE:-/root/.pgpass}"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/monitor}"
RETENER_DIAS="${RETENER_DIAS:-30}"

FECHA=$(date +"%Y-%m-%d_%H-%M-%S")
ARCHIVO="${BACKUP_DIR}/monitor_${FECHA}.sql.gz"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] iniciando respaldo de ${DB_DATABASE}..."

#dump y compresion en un paso el archivo nunca toca el disco sin comprimir
pg_dump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USER" \
    --no-owner \
    --no-acl \
    --format=plain \
    "$DB_DATABASE" \
  | gzip -9 > "$ARCHIVO"

#verificar integridad antes de declarar exito
if ! gunzip -t "$ARCHIVO" 2>/dev/null; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: archivo corrupto" >&2
    rm -f "$ARCHIVO"
    exit 1
fi

TAMANO=$(du -sh "$ARCHIVO" | cut -f1)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] respaldo OK: ${ARCHIVO} ${TAMANO}"

find "$BACKUP_DIR" -name "monitor_*.sql.gz" -mtime "+${RETENER_DIAS}" -delete
echo "[$(date '+%Y-%m-%d %H:%M:%S')] retencion aplicada archivos mayores a ${RETENER_DIAS} dias eliminados"
