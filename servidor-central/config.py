import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv()

_BASE_DIR = os.path.dirname(os.path.abspath(__file__))

DATABASE_URL = os.getenv(
    "DATABASE_URL",
    f"sqlite+aiosqlite:///{_BASE_DIR}/monitor.db",
)

def _leer_credencial(nombre: str) -> str:
    """Lee un secreto desde $CREDENTIALS_DIRECTORY (systemd LoadCredential).
    Si el directorio no esta disponible, devuelve cadena vacia."""
    cred_dir = os.environ.get("CREDENTIALS_DIRECTORY", "")
    if cred_dir:
        ruta = Path(cred_dir) / nombre
        if ruta.exists():
            return ruta.read_text().strip()
    return ""

ADMIN_API_KEY = _leer_credencial("admin_api_key") or os.getenv("ADMIN_API_KEY", "")
if not ADMIN_API_KEY:
    raise RuntimeError(
        "ADMIN_API_KEY no configurada — usa systemd LoadCredential "
        "o define la variable en .env"
    )

HEARTBEAT_INTERVALO_SEGUNDOS = int(os.getenv("HEARTBEAT_INTERVALO_SEGUNDOS", "30"))
HEARTBEAT_TIMEOUT_SEGUNDOS   = int(os.getenv("HEARTBEAT_TIMEOUT_SEGUNDOS", "90"))
VENTANA_HMAC_SEGUNDOS        = int(os.getenv("VENTANA_HMAC_SEGUNDOS", "60"))
AMBIENTE                     = os.getenv("AMBIENTE", "desarrollo")
