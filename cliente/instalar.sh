#!/bin/bash

set -e

if [ "$(id -u)" != "0" ]; then
    echo "error: ejecuta este script como root"
    echo "uso: sudo bash instalar.sh"
    exit 1
fi

DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== instalando demonio de heartbeat ==="
echo ""

if id "monitor-agent" &>/dev/null; then
    echo "[INFO] usuario monitor-agent ya existe"
else
    NOLOGIN=$(which nologin 2>/dev/null || echo /sbin/nologin)
    useradd --system --no-create-home --shell "$NOLOGIN" monitor-agent
    echo "[OK] usuario monitor-agent creado"
fi

echo "[...] instalando httpx..."
pip3 install httpx -q --break-system-packages 2>/dev/null || pip3 install httpx -q
echo "[OK] httpx listo"

mkdir -p /opt/monitor-agent
cp "$DIR/latidos.py" /opt/monitor-agent/latidos.py
chmod 755 /opt/monitor-agent/latidos.py
chown root:monitor-agent /opt/monitor-agent/latidos.py
echo "[OK] latidos.py copiado a /opt/monitor-agent/"

mkdir -p /etc/monitor-agent
if [ -f /etc/monitor-agent/config.env ]; then
    echo "[INFO] /etc/monitor-agent/config.env ya existe no se sobreescribe"
else
    cp "$DIR/config.env.example" /etc/monitor-agent/config.env
    chmod 600 /etc/monitor-agent/config.env
    chown monitor-agent:monitor-agent /etc/monitor-agent/config.env
    echo "[OK] plantilla copiada a /etc/monitor-agent/config.env"
fi

cp "$DIR/monitor-agent.service" /etc/systemd/system/monitor-agent.service
systemctl daemon-reload
echo "[OK] servicio systemd registrado"

echo ""
echo "=== instalacion completada ==="
echo ""
echo "edita la configuracion con los datos de este servidor:"
echo "  sudo nano /etc/monitor-agent/config.env"
echo ""
echo "cuando termines arranca e habilita el servicio:"
echo "  sudo systemctl start monitor-agent"
echo "  sudo systemctl enable monitor-agent"
echo ""
echo "verifica que funciona:"
echo "  sudo systemctl status monitor-agent"
echo "  sudo journalctl -u monitor-agent -f"
echo ""
