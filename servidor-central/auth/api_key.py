import secrets
from fastapi import HTTPException, Security
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from config import ADMIN_API_KEY
from logs import logger_seguridad

_esquema_bearer = HTTPBearer()


def validar_api_key(
    credenciales: HTTPAuthorizationCredentials = Security(_esquema_bearer),
):
    if not secrets.compare_digest(credenciales.credentials, ADMIN_API_KEY): #compare_digest previene timing attacks
        logger_seguridad.warning("api_key_invalida")  #no se registra el valor recibido
        raise HTTPException(status_code=401, detail="Clave API invalida")
