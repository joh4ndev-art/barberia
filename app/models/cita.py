from sqlalchemy import Column, Integer, String, DateTime, Boolean, Enum as SQLEnum
from app.db.database import Base
from datetime import datetime, timezone
import enum

# Estados posibles de una cita
class EstadoCita(str, enum.Enum):
    PENDIENTE = "pendiente"
    CONFIRMADA = "confirmada"
    CANCELADA = "cancelada"
    COMPLETADA = "completada"

# Modelo de la tabla "citas"
class Cita(Base):
    __tablename__ = "citas"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    nombre_cliente = Column(String(100), nullable=False)
    telefono = Column(String(20), nullable=False)
    email = Column(String(100), nullable=True)
    fecha_hora = Column(DateTime, nullable=False)
    servicio = Column(String(100), default="Corte de cabello")
    estado = Column(SQLEnum(EstadoCita), default=EstadoCita.PENDIENTE)
    google_event_id = Column(String(200), nullable=True)
    recordatorio_whatsapp_enviado = Column(Boolean, default=False)
    recordatorio_llamada_enviado = Column(Boolean, default=False)
    creado_en = Column(DateTime, default=lambda: datetime.now(timezone.utc))
    actualizado_en = Column(DateTime, default=lambda: datetime.now(timezone.utc), onupdate=lambda: datetime.now(timezone.utc))
