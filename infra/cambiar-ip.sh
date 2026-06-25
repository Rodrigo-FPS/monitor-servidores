#!/usr/bin/env bash
# Actualiza la IP del servidor central cuando cambia de red.
# Regenera el certificado SSL, actualiza APP_URL en el .env de Laravel
# y recarga nginx. Ejecutar como root en el servidor central.
#
# Uso:
#   sudo bash infra/cambiar-ip.sh              # detecta la IP automaticamente
#   sudo bash infra/cambiar-ip.sh 192.168.1.50 # usa la IP indicada
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "ERROR: ejecuta este script como root (sudo)." >&2
    exit 1
fi

# Detectar o aceptar IP
if [[ $# -ge 1 ]]; then
    NUEVA_IP="$1"
else
    NUEVA_IP=$(ip -4 addr show scope global \
        | grep 'inet ' \
        | awk '{print $2}' \
        | cut -d/ -f1 \
        | head -1)
fi

if [[ -z "$NUEVA_IP" ]]; then
    echo "ERROR: no se pudo detectar la IP. Pasala como argumento:" >&2
    echo "  sudo bash infra/cambiar-ip.sh 192.168.1.50" >&2
    exit 1
fi

# Validar formato IPv4 estricto antes de usar el valor en sed y openssl
if ! [[ "$NUEVA_IP" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    echo "ERROR: '$NUEVA_IP' no tiene formato IPv4 valido." >&2
    exit 1
fi
IFS='.' read -r -a _octetos <<< "$NUEVA_IP"
for _oct in "${_octetos[@]}"; do
    if (( _oct > 255 )); then
        echo "ERROR: octeto fuera de rango: $_oct" >&2
        exit 1
    fi
done

echo "Nueva IP: $NUEVA_IP"
echo ""

# 1. Regenerar certificado SSL autofirmado para la nueva IP
echo "[1/4] Regenerando certificado SSL..."
openssl req -x509 -nodes -newkey rsa:4096 -days 365 \
    -keyout /etc/ssl/monitor/privkey.pem \
    -out    /etc/ssl/monitor/fullchain.pem \
    -subj   "/CN=${NUEVA_IP}/O=Monitor/C=MX" \
    -addext "subjectAltName=IP:${NUEVA_IP}" \
    2>/dev/null
chmod 600 /etc/ssl/monitor/privkey.pem
chmod 644 /etc/ssl/monitor/fullchain.pem
echo "    OK: /etc/ssl/monitor/{fullchain,privkey}.pem"

# 2. Actualizar APP_URL en el .env de Laravel
# Se usa / como delimitador de sed para evitar conflicto con la / de https://
echo "[2/4] Actualizando APP_URL en /etc/monitor-laravel/.env..."
sed -i "s|^APP_URL=.*|APP_URL=https://${NUEVA_IP}|" /etc/monitor-laravel/.env
echo "    OK: APP_URL=https://${NUEVA_IP}"

# 3. Limpiar cache de Laravel (la URL queda cacheada en config)
echo "[3/4] Limpiando cache de configuracion de Laravel..."
sudo -u www-data php /var/www/monitor/artisan config:clear 2>/dev/null
sudo -u www-data php /var/www/monitor/artisan cache:clear  2>/dev/null
echo "    OK"

# 4. Recargar nginx con el nuevo certificado
echo "[4/4] Recargando nginx..."
nginx -t 2>/dev/null && systemctl reload nginx
echo "    OK"

echo ""
echo "Panel disponible en: https://${NUEVA_IP}"
echo ""
echo "Si hay agentes cliente en otras maquinas, actualizar SERVER_URL"
echo "en /etc/monitor-agent/config.env de cada una y reiniciar el agente:"
echo ""
echo "  sudo sed -i 's|^SERVER_URL=.*|SERVER_URL=https://${NUEVA_IP}|' /etc/monitor-agent/config.env"
echo "  sudo systemctl restart monitor-agent"
