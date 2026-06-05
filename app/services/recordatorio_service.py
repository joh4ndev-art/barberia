from datetime import datetime, timedelta
from app.db.database import SessionLocal
from app.models.cita import Cita, EstadoCita
from app.services.whatsapp_service import enviar_mensaje_whatsapp


def enviar_recordatorios():
    """
    Busca todas las citas confirmadas de mañana
    y envía recordatorios por WhatsApp.
    """
    db = SessionLocal()
    
    try:
        # Calcular el rango de mañana (00:00 a 23:59)
        manana = datetime.now().date() + timedelta(days=1)
        inicio = datetime.combine(manana, datetime.min.time())
        fin = datetime.combine(manana, datetime.max.time())
        
        # Buscar citas confirmadas para mañana que NO tengan recordatorio enviado
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
            # Formatear mensaje
            mensaje = (
                f"💈 *Recordatorio de tu cita*\n\n"
                f"Hola {cita.nombre_cliente}, te recordamos tu cita de mañana:\n"
                f"📅 Fecha: {cita.fecha_hora:%d/%m/%Y}\n"
                f"⏰ Hora: {cita.fecha_hora:%H:%M}\n"
                f"💇 Servicio: {cita.servicio}\n\n"
                f"Responde *SI* para confirmar o *NO* para cancelar."
            )
            
            # Asegurar que el teléfono tenga código de país
            telefono = cita.telefono
            if not telefono.startswith("57"):
                telefono = "57" + telefono
            
            # Enviar por WhatsApp
            exito = enviar_mensaje_whatsapp(telefono, mensaje)
            
            if exito:
                cita.recordatorio_whatsapp_enviado = True
                print(f"   ✅ Recordatorio enviado a {cita.nombre_cliente}")
            else:
                print(f"   ❌ Falló envío a {cita.nombre_cliente}")
        
        db.commit()
        print(f"✅ {datetime.now():%Y-%m-%d %H:%M} - Proceso terminado.")
        
    except Exception as e:
        print(f"❌ Error al enviar recordatorios: {e}")
        db.rollback()
    finally:
        db.close()