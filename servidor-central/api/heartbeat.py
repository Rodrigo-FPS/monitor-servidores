from fastapi import APIRouter, Request, Depends
from fastapi.responses import JSONResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from datetime import datetime, timezone

from db.database import obtener_sesion
from db.models import Servidor
from auth.firma_validator import validar_firma_ed25519, validar_timestamp
from logs import logger_seguridad, logger_estados

router = APIRouter()

_RESPUESTA_OK = {"status": "ok"} #respuesta generica igual para peticiones validas e invalidas


def _extraer_headers(request: Request):
    server_id      = request.headers.get("X-Server-ID", "").strip()
    timestamp_iso  = request.headers.get("X-Timestamp", "").strip()
    autorizacion   = request.headers.get("Authorization", "").strip()
    return server_id, timestamp_iso, autorizacion


def _extraer_firma(autorizacion: str):
    partes = autorizacion.split(" ", 1)
    if len(partes) != 2 or partes[0] != "Ed25519":
        return None
    return partes[1].strip()


def _obtener_ip_cliente(request: Request) -> str:
    #nginx fija X-Real-IP con $remote_addr usando proxy_set_header, que REEMPLAZA
    #cualquier valor que envie el cliente -> no es falsificable. No usamos
    #X-Forwarded-For ni request.client.host porque, con $proxy_add_x_forwarded_for
    #o proxy_headers de uvicorn, la parte izquierda la controla el cliente.
    #Si no hay proxy (acceso directo en loopback) se cae a la IP del socket.
    ip_real = request.headers.get("X-Real-IP", "").strip()
    if ip_real:
        return ip_real
    return request.client.host if request.client else ""


async def _buscar_servidor(server_id: str, sesion: AsyncSession):
    resultado = await sesion.execute(
        select(Servidor).where(Servidor.server_id == server_id)
    )
    return resultado.scalar_one_or_none()


def _rechazar(request: Request, server_id: str, motivo: str):
    #Registro de seguridad del rechazo. El rate-limit de nginx (zona heartbeat)
    #acota la frecuencia, evitando que este log sea un vector de DoS. No se
    #registran secretos (ni la firma ni la clave).
    logger_seguridad.warning(
        "rechazo path=%s ip=%s server_id=%s motivo=%s",
        request.url.path,
        _obtener_ip_cliente(request),
        (server_id or "-")[:64],
        motivo,
    )
    return None, False


async def validar_peticion_ed25519(request: Request, sesion: AsyncSession):
    server_id, timestamp_iso, autorizacion = _extraer_headers(request)

    if not server_id or not timestamp_iso or not autorizacion:
        return _rechazar(request, server_id, "headers_incompletos")

    firma = _extraer_firma(autorizacion)
    if firma is None:
        return _rechazar(request, server_id, "autorizacion_malformada")

    #Ed25519: firma base64 de 64 bytes = 88 chars; server_id max 64; timestamp max 35
    if len(server_id) > 64 or len(timestamp_iso) > 35 or len(firma) > 100:
        return _rechazar(request, server_id, "longitud_invalida")

    servidor = await _buscar_servidor(server_id, sesion)
    if servidor is None:
        return _rechazar(request, server_id, "servidor_no_registrado")

    if _obtener_ip_cliente(request) != servidor.ip_registrada:
        return _rechazar(request, server_id, "ip_no_coincide")

    if not validar_timestamp(timestamp_iso):
        return _rechazar(request, server_id, "timestamp_fuera_de_ventana")

    if not validar_firma_ed25519(servidor.clave_publica, server_id, timestamp_iso, firma):
        return _rechazar(request, server_id, "firma_invalida")

    return servidor, True


async def registrar_latido(servidor: Servidor, sesion: AsyncSession):
    #Registrar solo la transicion (no cada latido) para llevar el historial en el log.
    if servidor.estado != "encendido":
        logger_estados.info(
            "cambio_estado server_id=%s anterior=%s nuevo=encendido",
            servidor.server_id, servidor.estado,
        )
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
    servidor, valido = await validar_peticion_ed25519(request, sesion)

    if valido:
        await registrar_latido(servidor, sesion)

    return JSONResponse(_RESPUESTA_OK, status_code=200) #siempre 200 no revelar que validacion fallo
