import re
import secrets
import ipaddress

from fastapi import APIRouter, Request, Depends
from fastapi.responses import JSONResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select

from db.database import obtener_sesion
from db.models import Servidor
from auth.api_key import validar_api_key

router = APIRouter()

_PATRON_SERVER_ID = re.compile(r"^[a-zA-Z0-9_-]{1,64}$")
_PATRON_HOSTNAME  = re.compile(r"^[a-zA-Z0-9._-]{1,255}$")


def _es_ip_valida(ip: str) -> bool:
    try:
        ipaddress.ip_address(ip)
        return True
    except ValueError:
        return False


async def listar_servidores(sesion: AsyncSession) -> list:
    resultado = await sesion.execute(select(Servidor).order_by(Servidor.hostname))
    return list(resultado.scalars().all())


async def obtener_servidor(server_id: str, sesion: AsyncSession):
    resultado = await sesion.execute(
        select(Servidor).where(Servidor.server_id == server_id)
    )
    return resultado.scalar_one_or_none()


async def agregar_servidor(server_id: str, hostname: str, ip: str, sesion: AsyncSession) -> dict:
    if await obtener_servidor(server_id, sesion) is not None:
        return {"ok": False, "error": "El server_id ya existe"}

    secreto = secrets.token_hex(32) #secreto HMAC de 64 hex chars generado aleatoriamente
    nuevo = Servidor(
        server_id=server_id,
        hostname=hostname,
        ip_registrada=ip,
        secreto=secreto,
        estado="indeterminado",
    )
    sesion.add(nuevo)
    await sesion.commit()
    return {"ok": True, "server_id": server_id, "secreto": secreto} #el secreto se devuelve solo aqui


async def actualizar_servidor(server_id: str, nueva_ip: str, sesion: AsyncSession) -> dict:
    servidor = await obtener_servidor(server_id, sesion)
    if servidor is None:
        return {"ok": False, "error": "Servidor no encontrado"}
    servidor.ip_registrada = nueva_ip
    sesion.add(servidor)
    await sesion.commit()
    return {"ok": True}


async def eliminar_servidor(server_id: str, sesion: AsyncSession) -> dict:
    servidor = await obtener_servidor(server_id, sesion)
    if servidor is None:
        return {"ok": False, "error": "Servidor no encontrado"}
    await sesion.delete(servidor)
    await sesion.commit()
    return {"ok": True}


@router.get("/api/admin/servidores")
async def ruta_listar(
    _=Depends(validar_api_key),
    sesion: AsyncSession = Depends(obtener_sesion),
):
    servidores = await listar_servidores(sesion)
    return JSONResponse([
        {
            "server_id":    sv.server_id,
            "hostname":     sv.hostname,
            "ip":           sv.ip_registrada,
            "estado":       sv.estado,
            "ultimo_visto": sv.ultimo_visto.isoformat() if sv.ultimo_visto else None,
            "registrado_en": sv.registrado_en.isoformat(),
        }
        for sv in servidores
    ])


@router.post("/api/admin/servidores", status_code=201)
async def ruta_agregar(
    request: Request,
    _=Depends(validar_api_key),
    sesion: AsyncSession = Depends(obtener_sesion),
):
    try:
        datos = await request.json()
    except Exception:
        return JSONResponse({"error": "body JSON invalido"}, status_code=400)

    server_id = str(datos.get("server_id", "")).strip()
    hostname  = str(datos.get("hostname", "")).strip()
    ip        = str(datos.get("ip", "")).strip()

    if not server_id or not hostname or not ip:
        return JSONResponse({"error": "se requieren server_id hostname ip"}, status_code=400)

    if not _PATRON_SERVER_ID.match(server_id):
        return JSONResponse({"error": "server_id invalido solo alfanumericos guion y guion bajo"}, status_code=422)

    if not _PATRON_HOSTNAME.match(hostname):
        return JSONResponse({"error": "hostname invalido"}, status_code=422)

    if not _es_ip_valida(ip):
        return JSONResponse({"error": "IP invalida debe ser IPv4 o IPv6 valida"}, status_code=422)

    resultado = await agregar_servidor(server_id, hostname, ip, sesion)

    if not resultado["ok"]:
        return JSONResponse({"error": resultado["error"]}, status_code=409)

    return JSONResponse(
        {
            "server_id": resultado["server_id"],
            "secreto":   resultado["secreto"],
            "aviso":     "copia el secreto ahora no se puede recuperar despues",
        },
        status_code=201,
    )


@router.patch("/api/admin/servidores/{server_id}")
async def ruta_actualizar(
    server_id: str,
    request: Request,
    _=Depends(validar_api_key),
    sesion: AsyncSession = Depends(obtener_sesion),
):
    if not _PATRON_SERVER_ID.match(server_id):
        return JSONResponse({"error": "server_id invalido"}, status_code=422)

    try:
        datos = await request.json()
    except Exception:
        return JSONResponse({"error": "body JSON invalido"}, status_code=400)

    nueva_ip = str(datos.get("ip", "")).strip()

    if not nueva_ip:
        return JSONResponse({"error": "se requiere el campo ip"}, status_code=400)

    if not _es_ip_valida(nueva_ip):
        return JSONResponse({"error": "IP invalida"}, status_code=422)

    resultado = await actualizar_servidor(server_id, nueva_ip, sesion)

    if not resultado["ok"]:
        return JSONResponse({"error": resultado["error"]}, status_code=404)

    return JSONResponse({"ok": True})


@router.delete("/api/admin/servidores/{server_id}")
async def ruta_eliminar(
    server_id: str,
    _=Depends(validar_api_key),
    sesion: AsyncSession = Depends(obtener_sesion),
):
    if not _PATRON_SERVER_ID.match(server_id):
        return JSONResponse({"error": "server_id invalido"}, status_code=422)

    resultado = await eliminar_servidor(server_id, sesion)

    if not resultado["ok"]:
        return JSONResponse({"error": resultado["error"]}, status_code=404)

    return JSONResponse({"ok": True})
