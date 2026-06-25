import logging
import os
from logging.handlers import RotatingFileHandler

# Directorio de logs. systemd lo crea y exporta como $LOGS_DIRECTORY cuando el unit
# declara LogsDirectory=monitor. Si no se puede escribir, se cae a stdout (journald).
_LOG_DIR = os.getenv("LOGS_DIRECTORY") or os.getenv("LOG_DIR") or "/var/log/monitor"

_FORMATO = logging.Formatter(
    "%(asctime)s %(levelname)s %(name)s %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S%z",
)


def _crear_logger(nombre: str, archivo: str) -> logging.Logger:
    logger = logging.getLogger(nombre)
    logger.setLevel(logging.INFO)
    logger.propagate = False
    if logger.handlers:
        return logger
    try:
        os.makedirs(_LOG_DIR, exist_ok=True)
        handler: logging.Handler = RotatingFileHandler(
            os.path.join(_LOG_DIR, archivo),
            maxBytes=5 * 1024 * 1024,
            backupCount=5,
            encoding="utf-8",
        )
    except OSError:
        handler = logging.StreamHandler()  # fallback a stdout -> journald
    handler.setFormatter(_FORMATO)
    logger.addHandler(handler)
    return logger


# Eventos de seguridad: rechazos de latido/apagado y fallos de autenticacion.
# NUNCA registrar secretos (firmas, claves, tokens, contrasenas).
logger_seguridad = _crear_logger("monitor.seguridad", "seguridad.log")

# Cambios de estado de los servidores (solo transiciones, no cada latido).
logger_estados = _crear_logger("monitor.estados", "estados.log")
