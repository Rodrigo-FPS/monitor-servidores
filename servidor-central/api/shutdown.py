from fastapi import APIRouter, Request, Depends
from fastapi.responses import JSONResponse
from sqlalchemy.ext.asyncio import AsyncSession
from datetime import datetime, timezone

from db.database import obtener_sesion
from db.models import Servidor
from api.heartbeat import validar_peticion_ed25519

router = APIRouter()

_RESPUESTA_OK = {"status": "ok"}


async def registrar_apagado(servidor: Servidor, sesion: AsyncSession):
    servidor.ultimo_visto        = datetime.now(timezone.utc)
    servidor.estado              = "apagado"
    servidor.ultimo_tipo_mensaje = "shutdown"
    sesion.add(servidor)
    await sesion.commit()


@router.post("/api/shutdown")
async def recibir_shutdown(
    request: Request,
    sesion: AsyncSession = Depends(obtener_sesion),
):
    servidor, valido = await validar_peticion_ed25519(request, sesion)

    if valido:
        await registrar_apagado(servidor, sesion)

    return JSONResponse(_RESPUESTA_OK, status_code=200)
