<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Soportar ambas variantes de nombres en POST
    $barbero_id = (int)($_POST['barbero_id'] ?? $_POST['barbero'] ?? 0);
    $fecha      = trim($_POST['fecha'] ?? '');
    $hora       = trim($_POST['hora'] ?? '');
    $nombre     = trim($_POST['nombre_cliente'] ?? $_POST['nombre'] ?? '');
    $telefono   = trim($_POST['telefono_cliente'] ?? $_POST['telefono'] ?? '');
    $email      = trim($_POST['email_cliente'] ?? $_POST['email'] ?? '');

    if (empty($barbero_id) || empty($fecha) || empty($hora) || empty($nombre) || empty($telefono)) {
        die("Por favor completa todos los campos obligatorios.");
    }

    // Normalizar formato de hora HH:MM a HH:MM:SS para coincidir exactamente con la BD
    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    try {
        $pdo->beginTransaction();

        // 1. Verificar si el slot sigue disponible
        $stmtCheck = $pdo->prepare("SELECT id FROM disponibilidad_barberos 
                                    WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora AND estado = 'disponible' 
                                    FOR UPDATE");
        $stmtCheck->execute([
            ':barbero_id' => $barbero_id,
            ':fecha'      => $fecha,
            ':hora'       => $hora
        ]);

        if (!$stmtCheck->fetch()) {
            $pdo->rollBack();
            die("Lo sentimos, este turno ya ha sido reservado por otro usuario.");
        }

        // 2. Insertar los datos del cliente
        $stmtCliente = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (:nombre, :telefono, :email)");
        $stmtCliente->execute([
            ':nombre'   => $nombre,
            ':telefono' => $telefono,
            ':email'    => $email
        ]);
        $cliente_id = $pdo->lastInsertId();

        // 3. Crear la cita
        $stmtCita = $pdo->prepare("INSERT INTO citas (cliente_id, barbero_id, fecha, hora, estado) 
                                   VALUES (:cliente_id, :barbero_id, :fecha, :hora, 'pendiente')");
        $stmtCita->execute([
            ':cliente_id' => $cliente_id,
            ':barbero_id' => $barbero_id,
            ':fecha'      => $fecha,
            ':hora'       => $hora
        ]);

        // 4. Marcar la hora como ocupada
        $stmtUpdate = $pdo->prepare("UPDATE disponibilidad_barberos SET estado = 'ocupado' 
                                     WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora");
        $stmtUpdate->execute([
            ':barbero_id' => $barbero_id,
            ':fecha'      => $fecha,
            ':hora'       => $hora
        ]);

        $pdo->commit();

        // Redirección corregida al HTML principal con el modal
        header("Location: ../index.html?reserva=exitosa");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al procesar la reserva: " . $e->getMessage());
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>