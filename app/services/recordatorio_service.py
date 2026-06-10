from datetime import datetime, timedelta
from app.db.database import SessionLocal
from app.models.cita import Cita, EstadoCita
from app.services.email_service import enviar_recordatorio_correo


def enviar_recordatorios():
    """
    Busca todas las citas confirmadas de mañana
    y envía recordatorios por correo electrónico.
    """
    db = SessionLocal()
    
    try:
        # Calcular el rango de mañana
        manana = datetime.now().date() + timedelta(days=1)
        inicio = datetime.combine(manana, datetime.min.time())
        fin = datetime.combine(manana, datetime.max.time())
        
        # Buscar citas confirmadas para mañana sin recordatorio enviado
        citas = db.query(Cita).filter(
            Cita.fecha_hora >= inicio,
            Cita.fecha_hora <= fin,
            Cita.estado == EstadoCita.CONFIRMADA,
            Cita.recordatorio_whatsapp_enviado == False
        ).all()
        
        if not citas:
            print(f"📭 {datetime.now():%Y-%m-%d %H:%M} - No hay citas nuevas para recordar.")
            return
        
        # Enviar recordatorio a cada cita
        for cita in citas:
            if not cita.email:
                print(f"   ⚠️ {cita.nombre_cliente} no tiene email. Se omite.")
                continue
            
            exito = enviar_recordatorio_correo(
                nombre=cita.nombre_cliente,
                email=cita.email,
                fecha_hora=cita.fecha_hora,
                servicio=cita.servicio
            )
            
            if exito:
                cita.recordatorio_whatsapp_enviado = True
                print(f"   ✅ Recordatorio enviado a {cita.nombre_cliente} ({cita.email})")
            else:
                print(f"   ❌ Falló envío a {cita.nombre_cliente}")
        
        db.commit()
        print(f"✅ {datetime.now():%Y-%m-%d %H:%M} - Proceso terminado.")
        
    except Exception as e:
        print(f"❌ Error: {e}")
        db.rollback()
    finally:
        db.close()