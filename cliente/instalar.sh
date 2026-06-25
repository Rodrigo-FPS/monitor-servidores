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

# Crear usuario del agente sin login
if id "monitor-agent" &>/dev/null; then
    echo "[INFO] usuario monitor-agent ya existe"
else
    NOLOGIN=$(which nologin 2>/dev/null || echo /sbin/nologin)
    useradd --system --no-create-home --shell "$NOLOGIN" monitor-agent
    echo "[OK] usuario monitor-agent creado"
fi

# Instalar python3-venv si no está disponible en el sistema
if ! python3 -m venv --help > /dev/null 2>&1; then
    echo "[...] instalando python3-venv..."
    apt-get install -y python3-venv > /dev/null 2>&1
    echo "[OK] python3-venv instalado"
fi

mkdir -p /opt/monitor-agent

# Crear entorno virtual e instalar dependencias sin tocar el Python del sistema
echo "[...] creando entorno virtual e instalando dependencias..."
python3 -m venv /opt/monitor-agent/venv
/opt/monitor-agent/venv/bin/pip install -q -r "$DIR/requirements.txt"
echo "[OK] dependencias instaladas en /opt/monitor-agent/venv/"

cp "$DIR/latidos.py" /opt/monitor-agent/latidos.py
chmod 755 /opt/monitor-agent/latidos.py
chown root:monitor-agent /opt/monitor-agent/latidos.py
echo "[OK] latidos.py copiado a /opt/monitor-agent/"

mkdir -p /etc/monitor-agent
chown monitor-agent:monitor-agent /etc/monitor-agent
chmod 750 /etc/monitor-agent

if [ -f /etc/monitor-agent/config.env ]; then
    echo "[INFO] /etc/monitor-agent/config.env ya existe, no se sobreescribe"
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
echo "arranca el servicio una vez para generar las claves Ed25519:"
echo "  sudo systemctl start monitor-agent"
echo ""
echo "lee la clave publica generada:"
echo "  sudo cat /etc/monitor-agent/public.key"
echo ""
echo "registra el servidor en el panel web pegando esa clave publica"
echo ""
echo "habilita el servicio para que arranque con el sistema:"
echo "  sudo systemctl enable monitor-agent"
echo ""
echo "verifica que funciona:"
echo "  sudo systemctl status monitor-agent"
echo "  sudo journalctl -u monitor-agent -f"
echo ""
