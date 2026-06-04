from fastapi import FastAPI
from app.db.init_db import init_database
from app.routers.citas import router as citas_router

# Inicializar tablas en la base de datos
init_database()

app = FastAPI(
    title="💈 Barbería API",
    description="Sistema de gestión de citas para barbería - Proyecto Johan",
    version="1.0.0"
)

# Incluir todas las rutas bajo /api
app.include_router(citas_router, prefix="/api")

@app.get("/")
def bienvenida():
    return {
        "mensaje": "💈 Bienvenido a la API de la Barbería",
        "documentacion": "http://localhost:8000/docs",
        "autor": "Johan"
    }
