import hmac
import hashlib
from datetime import datetime, timezone
from config import VENTANA_HMAC_SEGUNDOS


def calcular_hmac(secreto_bytes: bytes, server_id: str, timestamp_iso: str) -> str:
    mensaje = f"{server_id}:{timestamp_iso}".encode("utf-8")
    firma = hmac.new(secreto_bytes, mensaje, hashlib.sha256)
    return firma.hexdigest()


def validar_timestamp(timestamp_iso: str) -> bool: #rechaza mensajes cuyo timestamp excede la ventana antireplay
    try:
        momento = datetime.fromisoformat(timestamp_iso)
        if momento.tzinfo is None:
            return False
        ahora = datetime.now(timezone.utc)
        diferencia = abs((ahora - momento).total_seconds())
        return diferencia <= VENTANA_HMAC_SEGUNDOS
    except (ValueError, TypeError):
        return False


def validar_hmac(secreto_bytes: bytes, server_id: str, timestamp_iso: str, hmac_recibido: str) -> bool:
    hmac_esperado = calcular_hmac(secreto_bytes, server_id, timestamp_iso)
    return hmac.compare_digest(hmac_esperado, hmac_recibido) #compare_digest previene timing attacks
