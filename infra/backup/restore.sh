#!/usr/bin/env bash

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-monitor_servidores}"
DB_USER="${DBA_DB_USER:-monitor_dba}"
export PGPASSWORD="${DBA_DB_PASS:-REEMPLAZAR}"

ARCHIVO="${1:-}"

if [[ -z "$ARCHIVO" ]]; then
    echo "uso: $0 <archivo_backup.sql.gz>"
    unset PGPASSWORD
    exit 1
fi

if [[ ! -f "$ARCHIVO" ]]; then
    echo "ERROR: archivo no encontrado: ${ARCHIVO}"
    unset PGPASSWORD
    exit 1
fi

echo "verificando integridad del archivo..."
if ! gunzip -t "$ARCHIVO"; then
    echo "ERROR: archivo corrupto"
    unset PGPASSWORD
    exit 1
fi

echo ""
echo "ADVERTENCIA: sobreescribira completamente la base de datos ${DB_DATABASE}"
read -r -p "escribe CONFIRMAR para continuar: " RESP

if [[ "$RESP" != "CONFIRMAR" ]]; then
    echo "restauracion cancelada"
    unset PGPASSWORD
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] restaurando desde ${ARCHIVO}..."
gunzip -c "$ARCHIVO" | psql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USER" \
    --dbname="$DB_DATABASE"

unset PGPASSWORD
echo "[$(date '+%Y-%m-%d %H:%M:%S')] restauracion completada"
