import os
from dotenv import load_dotenv

load_dotenv()

_BASE_DIR = os.path.dirname(os.path.abspath(__file__))

#desarrollo usa SQLite sin configurar DATABASE_URL en .env
#produccion usa PostgreSQL configurado en .env
DATABASE_URL = os.getenv(
    "DATABASE_URL",
    f"sqlite+aiosqlite:///{_BASE_DIR}/monitor.db",
)

#genera una clave con: python3 -c "import secrets; print(secrets.token_urlsafe(64))"
ADMIN_API_KEY = os.getenv("ADMIN_API_KEY", "")
if not ADMIN_API_KEY:
    raise RuntimeError(
        "ADMIN_API_KEY no esta configurada en .env\n"
        "genera una con: python3 -c \"import secrets; print(secrets.token_urlsafe(64))\""
    )

HEARTBEAT_INTERVALO_SEGUNDOS = int(os.getenv("HEARTBEAT_INTERVALO_SEGUNDOS", "30"))
HEARTBEAT_TIMEOUT_SEGUNDOS   = int(os.getenv("HEARTBEAT_TIMEOUT_SEGUNDOS", "90"))
VENTANA_HMAC_SEGUNDOS        = int(os.getenv("VENTANA_HMAC_SEGUNDOS", "60"))
AMBIENTE                     = os.getenv("AMBIENTE", "desarrollo")
