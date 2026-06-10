import yagmail
import os
from dotenv import load_dotenv

load_dotenv()

EMAIL_REMITENTE = os.getenv("EMAIL_REMITENTE")
EMAIL_PASSWORD = os.getenv("EMAIL_PASSWORD")

# Configurar yagmail una sola vez
yag = yagmail.SMTP(user=EMAIL_REMITENTE, password=EMAIL_PASSWORD)


def enviar_correo(destinatario: str, asunto: str, mensaje: str) -> bool:
    """
    Envía un correo electrónico profesional.
    
    Args:
        destinatario: Correo del cliente
        asunto: Asunto del correo
        mensaje: Contenido del correo
    
    Returns:
        True si se envió correctamente, False si hubo error
    """
    try:
        yag.send(
            to=destinatario,
            subject=asunto,
            contents=mensaje
        )
        print(f"✅ Correo enviado a {destinatario}: {asunto}")
        return True
    except Exception as e:
        print(f"❌ Error enviando correo a {destinatario}: {e}")
        return False


def enviar_recordatorio_correo(nombre: str, email: str, fecha_hora, servicio: str) -> bool:
    """
    Envía un correo de recordatorio de cita con formato profesional.
    """
    fecha = fecha_hora.strftime("%d/%m/%Y")
    hora = fecha_hora.strftime("%I:%M %p")
    
    asunto = f"💈 Recordatorio de tu cita - Barbería"
    
    mensaje = f"""
    <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; padding: 20px;">
        <div style="text-align: center; background-color: #1a1a1a; padding: 15px; border-radius: 10px 10px 0 0;">
            <h2 style="color: #fff; margin: 0;">💈 Barbería</h2>
        </div>
        
        <div style="padding: 20px;">
            <p>Hola <strong>{nombre}</strong>,</p>
            
            <p>Te recordamos que tienes una cita agendada:</p>
            
            <div style="background-color: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <p style="margin: 5px 0;">📅 <strong>Fecha:</strong> {fecha}</p>
                <p style="margin: 5px 0;">⏰ <strong>Hora:</strong> {hora}</p>
                <p style="margin: 5px 0;">💇 <strong>Servicio:</strong> {servicio}</p>
            </div>
            
            <p>Si necesitas cancelar o reagendar, responde este correo.</p>
            
            <p>¡Te esperamos! 🪒</p>
        </div>
        
        <div style="text-align: center; font-size: 12px; color: #999; padding: 10px;">
            <p>Este es un correo automático. No respondas directamente.</p>
        </div>
    </div>
    """
    
    return enviar_correo(email, asunto, mensaje)