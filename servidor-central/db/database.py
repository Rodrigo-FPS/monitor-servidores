from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession, async_sessionmaker
from db.models import Base
from config import DATABASE_URL

motor = create_async_engine(DATABASE_URL, echo=False)
FabricaSesion = async_sessionmaker(motor, expire_on_commit=False)


async def inicializar_base_datos():
    async with motor.begin() as conexion:
        await conexion.run_sync(Base.metadata.create_all)


async def obtener_sesion():
    async with FabricaSesion() as sesion:
        yield sesion
