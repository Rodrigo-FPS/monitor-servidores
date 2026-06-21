from sqlalchemy import Column, String, DateTime, Text
from sqlalchemy.orm import DeclarativeBase
from datetime import datetime, timezone


class Base(DeclarativeBase):
    pass


class Servidor(Base):
    __tablename__ = "servidores"

    server_id            = Column(String(64), primary_key=True)
    hostname             = Column(String(255), nullable=False)
    ip_registrada        = Column(String(45), nullable=False)
    secreto              = Column(Text, nullable=False)
    estado               = Column(String(20), nullable=False, default="indeterminado")
    ultimo_visto         = Column(DateTime(timezone=True), nullable=True)
    ultimo_tipo_mensaje  = Column(String(20), nullable=True)
    registrado_en        = Column(
        DateTime(timezone=True),
        nullable=False,
        default=lambda: datetime.now(timezone.utc),
    )
