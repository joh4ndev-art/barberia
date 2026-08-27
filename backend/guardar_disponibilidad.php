<?php
// 1. Manejo seguro de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

// Validar que el usuario haya iniciado sesión (Admin o Barbero)
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

// Asegurar que tomamos el ID correcto de la sesión
$barbero_id = $_SESSION['usuario_id'] ?? $_SESSION['barbero_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fecha_seleccionada = trim($_POST['fecha_agenda'] ?? '');
    $horas_seleccionadas = $_POST['horas'] ?? [];
    $hora_personalizada = trim($_POST['hora_nueva'] ?? '');

    // Procesar hora personalizada si fue ingresada
    if (!empty($hora_personalizada)) {
        if (strlen($hora_personalizada) === 5) {
            $hora_personalizada .= ':00'; // Ajustar formato HH:MM a HH:MM:SS
        }
        $horas_seleccionadas[] = $hora_personalizada;
    }

    if (empty($horas_seleccionadas) || empty($fecha_seleccionada)) {
        header("Location: ../dashboard.php?msg=vacio");
        exit();
    }

    try {
        // La restricción UNIQUE KEY en MySQL junto con INSERT IGNORE
        // evita insertar duplicados de la misma hora para la misma fecha/barbero
        $sql = "INSERT IGNORE INTO disponibilidad_barberos (barbero_id, fecha, hora, estado) 
                VALUES (:barbero_id, :fecha, :hora, 'disponible')";
        
        $stmt = $pdo->prepare($sql);

        foreach ($horas_seleccionadas as $hora) {
            $stmt->execute([
                ':barbero_id' => $barbero_id,
                ':fecha'      => $fecha_seleccionada,
                ':hora'       => $hora
            ]);
        }

        header("Location: ../dashboard.php?msg=horarios_guardados");
        exit();

    } catch (PDOException $e) {
        die("Error crítico al guardar la disponibilidad: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>