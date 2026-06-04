from datetime import datetime, timedelta
from app.db.database import SessionLocal
from app.models.cita import Cita, EstadoCita


def enviar_recordatorios():
    """
    Busca todas las citas confirmadas de mañana
    y simula el envío de recordatorios.
    """
    db = SessionLocal()
    
    try:
        # Calcular el rango de mañana (00:00 a 23:59)
        manana = datetime.now().date() + timedelta(days=1)
        inicio = datetime.combine(manana, datetime.min.time())
        fin = datetime.combine(manana, datetime.max.time())
        
        # Buscar citas confirmadas para mañana
        citas = db.query(Cita).filter(
            Cita.fecha_hora >= inicio,
            Cita.fecha_hora <= fin,
            Cita.estado == EstadoCita.CONFIRMADA
        ).all()
        
        if not citas:
            print(f"📭 {datetime.now():%Y-%m-%d %H:%M} - No hay citas para mañana.")
            return
        
        # Simular envío de recordatorio para cada cita
        for cita in citas:
            print(f"📩 RECORDATORIO enviado a {cita.nombre_cliente}")
            print(f"   Teléfono: {cita.telefono}")
            print(f"   Cita: {cita.fecha_hora:%d/%m/%Y a las %H:%M}")
            print(f"   Servicio: {cita.servicio}")
            print("-" * 40)
            
            # Marcar que ya se envió recordatorio (evita duplicados)
            cita.recordatorio_whatsapp_enviado = True
        
        db.commit()
        print(f"✅ {datetime.now():%Y-%m-%d %H:%M} - {len(citas)} recordatorios enviados.")
        
    except Exception as e:
        print(f"❌ Error al enviar recordatorios: {e}")
        db.rollback()
    finally:
        db.close()