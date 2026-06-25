#!/usr/bin/env bash

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
# Por defecto restaura la BD de Laravel. Para la de FastAPI exporta
# DB_DATABASE=monitor_fastapi y DBA_DB_USER=fastapi_dba.
DB_DATABASE="${DB_DATABASE:-monitor_laravel}"
DB_USER="${DBA_DB_USER:-laravel_dba}"
# La contrasena se lee desde /root/.pgpass (600 root:root).
# psql nunca recibe ni almacena la contrasena como argumento ni variable visible.
export PGPASSFILE="${PGPASSFILE:-/root/.pgpass}"

ARCHIVO="${1:-}"

if [[ -z "$ARCHIVO" ]]; then
    echo "uso: $0 <archivo_backup.sql.gz>"
    exit 1
fi

if [[ ! -f "$ARCHIVO" ]]; then
    echo "ERROR: archivo no encontrado: ${ARCHIVO}"
    exit 1
fi

echo "verificando integridad del archivo..."
if ! gunzip -t "$ARCHIVO"; then
    echo "ERROR: archivo corrupto"
    exit 1
fi

echo ""
echo "ADVERTENCIA: sobreescribira completamente la base de datos ${DB_DATABASE}"
read -r -p "escribe CONFIRMAR para continuar: " RESP

if [[ "$RESP" != "CONFIRMAR" ]]; then
    echo "restauracion cancelada"
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] restaurando desde ${ARCHIVO}..."
gunzip -c "$ARCHIVO" | psql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USER" \
    --dbname="$DB_DATABASE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] restauracion completada"
