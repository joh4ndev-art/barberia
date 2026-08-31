<?php
// 1. Manejo seguro de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

// Validar que el usuario haya iniciado sesión (Admin, Jefe o Barbero)
if (!isset($_SESSION['barbero_id']) && !isset($_SESSION['usuario_id'])) {
    header("Location: ../login.html");
    exit();
}

// Obtener el ID activo correctamente
$barbero_id = $_SESSION['barbero_id'] ?? $_SESSION['usuario_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recibir campos del formulario del dashboard
    $fecha = trim($_POST['fecha'] ?? $_POST['fecha_agenda'] ?? '');
    $hora  = trim($_POST['hora'] ?? $_POST['hora_nueva'] ?? '');

    // Si viene un array de horas seleccionadas (por checkboxes)
    $horas_seleccionadas = $_POST['horas'] ?? [];

    // Si se envió una hora individual (del input type="time")
    if (!empty($hora)) {
        if (strlen($hora) === 5) {
            $hora .= ':00'; // Formatear HH:MM a HH:MM:SS para MySQL
        }
        $horas_seleccionadas[] = $hora;
    }

    // Validar datos mínimos obligatorios
    if (empty($fecha) || empty($horas_seleccionadas)) {
        header("Location: ../admin_dashboard.php?msg=vacio");
        exit();
    }

    // Validación de fecha en servidor
    date_default_timezone_set('America/Bogota');
    $fecha_hoy  = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Bloquear si la fecha elegida es anterior a hoy
    if ($fecha < $fecha_hoy) {
        header("Location: ../admin_dashboard.php?msg=fecha_invalida");
        exit();
    }

    // Filtrar horas pasadas si la fecha seleccionada es el día de hoy
    if ($fecha === $fecha_hoy) {
        $horas_validas = [];
        foreach ($horas_seleccionadas as $h) {
            // Asegurar formato HH:MM:SS para comparar correctamente
            $h_formato = (strlen($h) === 5) ? $h . ':00' : $h;
            if ($h_formato >= $hora_actual) {
                $horas_validas[] = $h_formato;
            }
        }
        $horas_seleccionadas = $horas_validas;
    }

    // Si todas las horas enviadas para hoy ya pasaron
    if (empty($horas_seleccionadas)) {
        header("Location: ../admin_dashboard.php?msg=hora_pasada");
        exit();
    }

    try {
        // Evita duplicados si la tabla tiene restricción UNIQUE (barbero_id, fecha, hora)
        $sql = "INSERT IGNORE INTO disponibilidad_barberos (barbero_id, fecha, hora, estado) 
                VALUES (:barbero_id, :fecha, :hora, 'disponible')";
        
        $stmt = $pdo->prepare($sql);

        $insertados = 0;
        foreach ($horas_seleccionadas as $h) {
            $stmt->execute([
                ':barbero_id' => $barbero_id,
                ':fecha'      => $fecha,
                ':hora'       => $h
            ]);
            
            if ($stmt->rowCount() > 0) {
                $insertados++;
            }
        }

        // Notificar si se guardó correctamente o si ya existía el bloque
        if ($insertados > 0) {
            header("Location: ../admin_dashboard.php?msg=horarios_guardados");
        } else {
            header("Location: ../admin_dashboard.php?msg=horario_duplicado");
        }
        exit();

    } catch (PDOException $e) {
        die("Error crítico al guardar la disponibilidad: " . $e->getMessage());
    }
} else {
    header("Location: ../admin_dashboard.php");
    exit();
}
?>