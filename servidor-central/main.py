import asyncio
from contextlib import asynccontextmanager
from datetime import datetime, timezone, timedelta

from fastapi import FastAPI
from sqlalchemy import select

from config import HEARTBEAT_INTERVALO_SEGUNDOS, HEARTBEAT_TIMEOUT_SEGUNDOS
from db.database import FabricaSesion
from db.models import Servidor
from api.heartbeat import router as router_heartbeat
from api.shutdown import router as router_shutdown
from api.servidores import router as router_servidores
from middleware.security_headers import MiddlewareSeguridad
from logs import logger_estados


async def actualizar_estados_servidores():
    limite = datetime.now(timezone.utc) - timedelta(seconds=HEARTBEAT_TIMEOUT_SEGUNDOS) #servidores sin latido antes de este momento quedan indeterminado

    async with FabricaSesion() as sesion:
        resultado = await sesion.execute(select(Servidor))
        servidores = resultado.scalars().all()

        for servidor in servidores:
            estado_anterior = servidor.estado

            if servidor.ultimo_tipo_mensaje == "shutdown":
                nuevo_estado = "apagado"
            elif servidor.ultimo_visto is None:
                nuevo_estado = "indeterminado"
            else:
                ultimo = servidor.ultimo_visto
                if ultimo.tzinfo is None:
                    ultimo = ultimo.replace(tzinfo=timezone.utc)
                nuevo_estado = "encendido" if ultimo >= limite else "indeterminado"

            servidor.estado = nuevo_estado

            #Se registra solo la TRANSICION de estado (no cada latido) para que el
            #historial quede en el log y la BD conserve unicamente el estado actual.
            if nuevo_estado != estado_anterior:
                logger_estados.info(
                    "cambio_estado server_id=%s anterior=%s nuevo=%s",
                    servidor.server_id, estado_anterior, nuevo_estado,
                )

            sesion.add(servidor)

        await sesion.commit()


async def job_estados():
    while True:
        try:
            await actualizar_estados_servidores()
        except Exception as error:
            logger_estados.error("job de estados fallo: %s", error)
        await asyncio.sleep(HEARTBEAT_INTERVALO_SEGUNDOS)


@asynccontextmanager
async def ciclo_de_vida(app: FastAPI):
    #El esquema se crea con db/schema.sql usando el rol DBA (minimos privilegios).
    #La app NO crea tablas en runtime (monitor_app no tiene privilegio CREATE).
    tarea = asyncio.create_task(job_estados())
    yield
    tarea.cancel()
    try:
        await tarea
    except asyncio.CancelledError:
        pass


app = FastAPI(
    title="Monitor de Servidores API",
    lifespan=ciclo_de_vida,
    docs_url=None,     #Swagger UI deshabilitado siempre
    redoc_url=None,    #ReDoc deshabilitado siempre
    openapi_url=None,  #no exponer el esquema de la API (/openapi.json)
)

app.add_middleware(MiddlewareSeguridad)

app.include_router(router_heartbeat)
app.include_router(router_shutdown)
app.include_router(router_servidores)


@app.get("/")
async def raiz():
    return {"status": "ok"}
