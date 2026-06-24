import base64
from datetime import datetime, timezone

from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from cryptography.hazmat.primitives.serialization import load_pem_public_key
from cryptography.exceptions import InvalidSignature

from config import VENTANA_ANTIREPLAY_SEGUNDOS


def validar_timestamp(timestamp_iso: str) -> bool:
    try:
        momento = datetime.fromisoformat(timestamp_iso)
        if momento.tzinfo is None:
            return False
        ahora = datetime.now(timezone.utc)
        diferencia = abs((ahora - momento).total_seconds())
        return diferencia <= VENTANA_ANTIREPLAY_SEGUNDOS
    except (ValueError, TypeError):
        return False


def validar_firma_ed25519(clave_publica_pem: str, server_id: str, timestamp_iso: str, firma_b64: str) -> bool:
    try:
        clave = load_pem_public_key(clave_publica_pem.encode("utf-8"))
        if not isinstance(clave, Ed25519PublicKey):
            return False
        mensaje     = f"{server_id}:{timestamp_iso}".encode("utf-8")
        firma_bytes = base64.b64decode(firma_b64)
        clave.verify(firma_bytes, mensaje) #lanza InvalidSignature si la firma no coincide
        return True
    except (InvalidSignature, Exception):
        return False
