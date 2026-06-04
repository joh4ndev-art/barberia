from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from dotenv import load_dotenv
import os

# Cargar variables del archivo .env
load_dotenv()

# URL de conexión a MySQL
DATABASE_URL = os.getenv("DATABASE_URL")

# Motor de base de datos
engine = create_engine(DATABASE_URL, echo=True)

# Fábrica de sesiones (cada petición usa una sesión nueva)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Base para declarar modelos
Base = declarative_base()

# Dependencia que inyecta la sesión en las rutas
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
