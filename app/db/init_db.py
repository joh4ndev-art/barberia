from app.db.database import engine, Base
from app.models.cita import Cita  # Importante: registra el modelo

def init_database():
    """Crea todas las tablas en MySQL si no existen"""
    Base.metadata.create_all(bind=engine)
    print("✅ Base de datos inicializada correctamente")

if __name__ == "__main__":
    init_database()
