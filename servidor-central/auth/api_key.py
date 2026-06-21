import secrets
from fastapi import HTTPException, Security
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from config import ADMIN_API_KEY

_esquema_bearer = HTTPBearer()


def validar_api_key(
    credenciales: HTTPAuthorizationCredentials = Security(_esquema_bearer),
):
    if not secrets.compare_digest(credenciales.credentials, ADMIN_API_KEY): #compare_digest previene timing attacks
        raise HTTPException(status_code=401, detail="Clave API invalida")
