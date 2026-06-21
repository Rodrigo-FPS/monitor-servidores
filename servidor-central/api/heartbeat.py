from fastapi import APIRouter, Request, Depends
from fastapi.responses import JSONResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from datetime import datetime, timezone

from db.database import obtener_sesion
from db.models import Servidor
from auth.hmac_validator import validar_hmac, validar_timestamp

router = APIRouter()

_RESPUESTA_OK = {"status": "ok"} #respuesta generica igual para peticiones validas e invalidas


def _extraer_headers(request: Request):
    server_id      = request.headers.get("X-Server-ID", "").strip()
    timestamp_iso  = request.headers.get("X-Timestamp", "").strip()
    autorizacion   = request.headers.get("Authorization", "").strip()
    return server_id, timestamp_iso, autorizacion


def _extraer_firma(autorizacion: str):
    partes = autorizacion.split(" ", 1)
    if len(partes) != 2 or partes[0] != "HMAC":
        return None
    return partes[1].strip()


async def _buscar_servidor(server_id: str, sesion: AsyncSession):
    resultado = await sesion.execute(
        select(Servidor).where(Servidor.server_id == server_id)
    )
    return resultado.scalar_one_or_none()


async def validar_peticion_hmac(request: Request, sesion: AsyncSession):
    #valida en orden: headers presentes IP registrada timestamp HMAC
    server_id, timestamp_iso, autorizacion = _extraer_headers(request)

    if not server_id or not timestamp_iso or not autorizacion:
        return None, False

    firma = _extraer_firma(autorizacion)
    if firma is None:
        return None, False

    #limite de longitud para prevenir ataques de buffer
    if len(server_id) > 64 or len(timestamp_iso) > 35 or len(firma) > 128:
        return None, False

    servidor = await _buscar_servidor(server_id, sesion)
    if servidor is None:
        return None, False

    if request.client.host != servidor.ip_registrada: #solo acepta de la IP registrada
        return None, False

    if not validar_timestamp(timestamp_iso):
        return None, False

    secreto_bytes = servidor.secreto.encode("utf-8")
    if not validar_hmac(secreto_bytes, server_id, timestamp_iso, firma):
        return None, False

    return servidor, True


async def registrar_latido(servidor: Servidor, sesion: AsyncSession):
    servidor.ultimo_visto         = datetime.now(timezone.utc)
    servidor.estado               = "encendido"
    servidor.ultimo_tipo_mensaje  = "heartbeat"
    sesion.add(servidor)
    await sesion.commit()


@router.post("/api/heartbeat")
async def recibir_heartbeat(
    request: Request,
    sesion: AsyncSession = Depends(obtener_sesion),
):
    servidor, valido = await validar_peticion_hmac(request, sesion)

    if valido:
        await registrar_latido(servidor, sesion)

    return JSONResponse(_RESPUESTA_OK, status_code=200) #siempre 200 no revelar que validacion fallo
