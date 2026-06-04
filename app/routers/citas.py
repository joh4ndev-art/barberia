from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from app.db.database import get_db
from app.models.cita import Cita, EstadoCita
from datetime import datetime, timezone
from pydantic import BaseModel, Field
from typing import Optional

router = APIRouter()

# --- Schemas de validación ---

class CitaCreate(BaseModel):
    """Datos que envía el cliente para crear una cita"""
    nombre_cliente: str = Field(..., min_length=2, max_length=100, example="Juan Pérez")
    telefono: str = Field(..., min_length=7, max_length=20, example="3001234567")
    email: Optional[str] = None
    fecha_hora: datetime = Field(..., example="2026-06-10T15:00:00")
    servicio: Optional[str] = "Corte de cabello"

class CitaResponse(BaseModel):
    """Datos que devuelve la API al crear/consultar una cita"""
    id: int
    nombre_cliente: str
    telefono: str
    email: Optional[str]
    fecha_hora: datetime
    servicio: str
    estado: str
    creado_en: datetime

    class Config:
        from_attributes = True

# --- Endpoints ---

@router.get("/")
def raiz():
    return {
        "mensaje": "💈 API de Barbería funcionando",
        "endpoints": {
            "POST /citas": "Crear una nueva cita",
            "GET /citas": "Listar todas las citas",
            "GET /citas/{id}": "Ver una cita específica",
            "PATCH /citas/{id}/confirmar": "Confirmar una cita",
            "DELETE /citas/{id}": "Cancelar/Eliminar una cita"
        }
    }

@router.post("/citas", response_model=CitaResponse, status_code=201)
def crear_cita(cita: CitaCreate, db: Session = Depends(get_db)):
    """Crea una nueva cita si el horario está disponible"""
    
    # Verificar si ya hay una cita activa en esa fecha y hora
    existe = db.query(Cita).filter(
        Cita.fecha_hora == cita.fecha_hora,
        Cita.estado.in_([EstadoCita.PENDIENTE, EstadoCita.CONFIRMADA])
    ).first()
    
    if existe:
        raise HTTPException(
            status_code=409,
            detail=f"El horario {cita.fecha_hora} ya está ocupado"
        )
    
    # Crear la cita
    nueva_cita = Cita(
        nombre_cliente=cita.nombre_cliente,
        telefono=cita.telefono,
        email=cita.email,
        fecha_hora=cita.fecha_hora,
        servicio=cita.servicio,
        estado=EstadoCita.PENDIENTE
    )
    
    db.add(nueva_cita)
    db.commit()
    db.refresh(nueva_cita)
    
    return nueva_cita

@router.get("/citas", response_model=list[CitaResponse])
def listar_citas(db: Session = Depends(get_db)):
    """Lista todas las citas ordenadas por fecha"""
    return db.query(Cita).order_by(Cita.fecha_hora).all()

@router.get("/citas/{cita_id}", response_model=CitaResponse)
def obtener_cita(cita_id: int, db: Session = Depends(get_db)):
    """Obtiene una cita específica por su ID"""
    
    cita = db.query(Cita).filter(Cita.id == cita_id).first()
    
    if not cita:
        raise HTTPException(status_code=404, detail="Cita no encontrada")
    
    return cita

@router.patch("/citas/{cita_id}/confirmar", response_model=CitaResponse)
def confirmar_cita(cita_id: int, db: Session = Depends(get_db)):
    """Confirma una cita pendiente"""
    
    cita = db.query(Cita).filter(Cita.id == cita_id).first()
    
    if not cita:
        raise HTTPException(status_code=404, detail="Cita no encontrada")
    
    if cita.estado != EstadoCita.PENDIENTE:
        raise HTTPException(
            status_code=400,
            detail=f"No se puede confirmar una cita en estado '{cita.estado.value}'"
        )
    
    cita.estado = EstadoCita.CONFIRMADA
    db.commit()
    db.refresh(cita)
    
    return cita

@router.delete("/citas/{cita_id}")
def cancelar_cita(cita_id: int, db: Session = Depends(get_db)):
    """Cancela una cita (la marca como cancelada)"""
    
    cita = db.query(Cita).filter(Cita.id == cita_id).first()
    
    if not cita:
        raise HTTPException(status_code=404, detail="Cita no encontrada")
    
    if cita.estado == EstadoCita.COMPLETADA:
        raise HTTPException(
            status_code=400,
            detail="No se puede cancelar una cita ya completada"
        )
    
    cita.estado = EstadoCita.CANCELADA
    db.commit()
    
    return {"mensaje": f"Cita #{cita_id} cancelada exitosamente"}
