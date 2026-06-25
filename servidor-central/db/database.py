from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker
from config import DATABASE_URL

motor = create_async_engine(DATABASE_URL, echo=False)
FabricaSesion = async_sessionmaker(motor, expire_on_commit=False)


async def obtener_sesion():
    async with FabricaSesion() as sesion:
        yield sesion
