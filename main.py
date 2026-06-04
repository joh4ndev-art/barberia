from fastapi import FastAPI
from apscheduler.schedulers.background import BackgroundScheduler
from app.db.init_db import init_database
from app.routers.citas import router as citas_router
from app.services.recordatorio_service import enviar_recordatorios

# Inicializar tablas en la base de datos
init_database()

app = FastAPI(
    title="💈 Barbería API",
    description="Sistema de gestión de citas para barbería - Proyecto Johan",
    version="1.0.0"
)

# Incluir todas las rutas bajo /api
app.include_router(citas_router, prefix="/api")

# --- Programador de tareas ---
scheduler = BackgroundScheduler()
scheduler.add_job(
    enviar_recordatorios,      # Función a ejecutar
    "cron",                     # Tipo: programación cron
    hour=20,                    # Hora: 8 PM
    minute=0,                   # Minuto: 00
    id="recordatorio_diario"   # Identificador único
)
scheduler.start()
print("⏰ Programador de recordatorios iniciado (8:00 PM todos los días)")

# --- Endpoint para probar manualmente ---
@app.post("/api/enviar-recordatorios")
def forzar_recordatorios():
    """Endpoint para probar el envío de recordatorios manualmente"""
    enviar_recordatorios()
    return {"mensaje": "Recordatorios enviados (revisa la consola)"}


@app.on_event("shutdown")
def apagar_scheduler():
    """Detiene el programador al cerrar la API"""
    scheduler.shutdown()


@app.get("/")
def bienvenida():
    return {
        "mensaje": "💈 Bienvenido a la API de la Barbería",
        "documentacion": "http://localhost:8000/docs",
        "autor": "Johan"
    }