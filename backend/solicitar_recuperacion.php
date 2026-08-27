<?php
require_once 'conexion.php';

// Establecemos zona horaria para Colombia
date_default_timezone_set('America/Bogota');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo']);

    if (empty($correo)) {
        die("Por favor ingresa un correo electrónico.");
    }

    // Tu cuenta de correo de Hostinger configurada
    $correo_remitente = 'serviciojohandev@johandev.com.co'; // <-- TU NUEVO CORREO DE HOSTINGER
    $password_remitente = 'Servici0??'; // <-- LA CONTRASEÑA DE ESE CORREO

    try {
        // 1. Verificar si el correo pertenece a un barbero registrado
        $stmt = $pdo->prepare("SELECT id, nombre FROM barberos WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        $barbero = $stmt->fetch();

        if ($barbero) {
            // 2. Generar un Token único y seguro de recuperación
            $token = bin2hex(random_bytes(32));
            
            // Definimos que el enlace expire en 1 hora
            $expira = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // 3. Guardar el token en la tabla (Limpiamos tokens viejos de ese correo primero)
            $stmtDel = $pdo->prepare("DELETE FROM recuperacion_pass WHERE correo = :correo");
            $stmtDel->execute([':correo' => $correo]);

            $stmtIns = $pdo->prepare("INSERT INTO recuperacion_pass (correo, token, expira) VALUES (:correo, :token, :expira)");
            $stmtIns->execute([
                ':correo' => $correo,
                ':token' => $token,
                ':expira' => $expira
            ]);

            // 4. Crear el enlace que irá dentro del correo electrónico
            $enlace_recuperacion = "https://johandev.com.co/restablecer.php?token=" . $token;

            // 5. Estructurar el cuerpo del correo en HTML limpio y moderno
            $asunto = "Restablecer Contraseña - Barberia J.T";
            $mensaje = "
            <html>
            <head>
                <title>Restablecer Contraseña</title>
            </head>
            <body style='background-color: #111827; font-family: sans-serif; color: #f3f4f6; padding: 20px;'>
                <div style='max-width: 500px; margin: 0 auto; background-color: #1f2937; padding: 30px; border-radius: 12px; border: 1px solid #374151;'>
                    <h2 style='color: #f59e0b; text-align: center;'>Barberia J.T</h2>
                    <p>Hola <strong>" . htmlspecialchars($barbero['nombre']) . "</strong>,</p>
                    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en el dashboard.</p>
                    <p>Para continuar, haz clic en el siguiente botón seguro. Este enlace vencerá en 1 hora:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $enlace_recuperacion . "' style='background-color: #10b981; color: #030712; font-weight: bold; padding: 12px 24px; text-decoration: none; border-radius: 8px;'>RESTABLECER CONTRASEÑA</a>
                    </div>
                    <p style='font-size: 11px; color: #9ca3af; text-align: center;'>Si tú no solicitaste este cambio, puedes ignorar este correo de forma segura.</p>
                </div>
            </body>
            </html>
            ";

            // Cabeceras obligatorias para enviar HTML
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Barberia J.T <" . $correo_remitente . ">" . "\r\n";

            // Enviar el correo usando el servidor de Hostinger
            mail($correo, $asunto, $mensaje, $headers);
        }

        // Redirigimos al login con éxito
        header("Location: ../login.html?status=correo_enviado");
        exit();

    } catch (PDOException $e) {
        die("Error en el proceso de recuperación: " . $e->getMessage());
    }
} else {
    header("Location: ../login.html");
    exit();
}
?>