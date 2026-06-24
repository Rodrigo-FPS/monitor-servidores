import asyncio
from contextlib import asynccontextmanager
from datetime import datetime, timezone, timedelta

from fastapi import FastAPI
from sqlalchemy import select

from config import HEARTBEAT_INTERVALO_SEGUNDOS, HEARTBEAT_TIMEOUT_SEGUNDOS, AMBIENTE
from db.database import inicializar_base_datos, FabricaSesion
from db.models import Servidor
from api.heartbeat import router as router_heartbeat
from api.shutdown import router as router_shutdown
from api.servidores import router as router_servidores
from middleware.security_headers import MiddlewareSeguridad


async def actualizar_estados_servidores():
    limite = datetime.now(timezone.utc) - timedelta(seconds=HEARTBEAT_TIMEOUT_SEGUNDOS) #servidores sin latido antes de este momento quedan indeterminado

    async with FabricaSesion() as sesion:
        resultado = await sesion.execute(select(Servidor))
        servidores = resultado.scalars().all()

        for servidor in servidores:
            if servidor.ultimo_tipo_mensaje == "shutdown":
                servidor.estado = "apagado"
            elif servidor.ultimo_visto is None:
                servidor.estado = "indeterminado"
            else:
                ultimo = servidor.ultimo_visto
                if ultimo.tzinfo is None:
                    ultimo = ultimo.replace(tzinfo=timezone.utc)
                servidor.estado = "encendido" if ultimo >= limite else "indeterminado"

            sesion.add(servidor)

        await sesion.commit()


async def job_estados():
    while True:
        try:
            await actualizar_estados_servidores()
        except Exception as error:
            print(f"[ERROR] job de estados: {error}")
        await asyncio.sleep(HEARTBEAT_INTERVALO_SEGUNDOS)


@asynccontextmanager
async def ciclo_de_vida(app: FastAPI):
    #En produccion el esquema se crea con db/schema.sql usando el rol DBA; el rol de
    #runtime (monitor_app) sigue el principio de minimos privilegios y NO tiene CREATE.
    #create_all solo se usa fuera de produccion para facilitar el desarrollo.
    if AMBIENTE != "produccion":
        await inicializar_base_datos()
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
    docs_url=None if AMBIENTE == "produccion" else "/docs",
    redoc_url=None,
)

app.add_middleware(MiddlewareSeguridad)

app.include_router(router_heartbeat)
app.include_router(router_shutdown)
app.include_router(router_servidores)


@app.get("/")
async def raiz():
    return {"status": "ok"}
