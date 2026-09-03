<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $barbero_id = (int)($_POST['barbero_id'] ?? $_POST['barbero'] ?? 0);
    $fecha      = trim($_POST['fecha'] ?? '');
    $hora       = trim($_POST['hora'] ?? '');
    $nombre     = trim($_POST['nombre_cliente'] ?? $_POST['nombre'] ?? '');
    $telefono   = trim($_POST['telefono_cliente'] ?? $_POST['telefono'] ?? '');
    $email      = trim($_POST['email_cliente'] ?? $_POST['email'] ?? '');
    $notas      = trim($_POST['notas'] ?? ''); // <--- Captura de la descripción del corte

    if (empty($barbero_id) || empty($fecha) || empty($hora) || empty($nombre) || empty($telefono)) {
        die("Por favor completa todos los campos obligatorios.");
    }

    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    try {
        $pdo->beginTransaction();

        // 1. Verificar disponibilidad
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

        // 3. Crear la cita (incluyendo la columna notas)
        $stmtCita = $pdo->prepare("INSERT INTO citas (cliente_id, barbero_id, fecha, hora, estado, notas) 
                                   VALUES (:cliente_id, :barbero_id, :fecha, :hora, 'pendiente', :notas)");
        $stmtCita->execute([
            ':cliente_id' => $cliente_id,
            ':barbero_id' => $barbero_id,
            ':fecha'      => $fecha,
            ':hora'       => $hora,
            ':notas'      => $notas
        ]);

        // 4. Marcar la hora como ocupada
        $stmtUpdate = $pdo->prepare("UPDATE disponibilidad_barberos SET estado = 'ocupado' 
                                     WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora");
        $stmtUpdate->execute([
            ':barbero_id' => $barbero_id,
            ':fecha'      => $fecha,
            ':hora'       => $hora
        ]);

        // 5. Consultar nombre del barbero
        $stmtBarbero = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = :id");
        $stmtBarbero->execute([':id' => $barbero_id]);
        $barberoData = $stmtBarbero->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();

        $nombreBarbero   = urlencode($barberoData['nombre'] ?? '');
        $telefonoGeneral = urlencode('+573001234567');

        header("Location: ../index.html?reserva=exitosa&barbero={$nombreBarbero}&telefono={$telefonoGeneral}");
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