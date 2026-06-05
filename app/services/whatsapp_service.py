import httpx
import os
from dotenv import load_dotenv

load_dotenv()

WHATSAPP_TOKEN = os.getenv("WHATSAPP_TOKEN")
WHATSAPP_PHONE_ID = os.getenv("WHATSAPP_PHONE_ID")
WHATSAPP_API_URL = f"https://graph.facebook.com/v21.0/{WHATSAPP_PHONE_ID}/messages"


def enviar_mensaje_whatsapp(telefono: str, mensaje: str) -> bool:
    """
    Envía un mensaje de WhatsApp a un número de teléfono.
    
    Args:
        telefono: Número en formato internacional (ej: 573001234567)
        mensaje: Texto del mensaje a enviar
    
    Returns:
        True si se envió correctamente, False si hubo error
    """
    headers = {
        "Authorization": f"Bearer {WHATSAPP_TOKEN}",
        "Content-Type": "application/json"
    }
    
    data = {
        "messaging_product": "whatsapp",
        "to": telefono,
        "type": "text",
        "text": {
            "body": mensaje
        }
    }
    
    try:
        response = httpx.post(WHATSAPP_API_URL, headers=headers, json=data)
        response.raise_for_status()
        print(f"✅ WhatsApp enviado a {telefono}: {mensaje[:50]}...")
        return True
    except Exception as e:
        print(f"❌ Error enviando WhatsApp a {telefono}: {e}")
        return False