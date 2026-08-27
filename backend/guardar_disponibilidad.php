<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $barbero_id = $_SESSION['barbero_id'];
    
    // Capturamos la fecha elegida por el barbero
    $fecha_seleccionada = $_POST['fecha_agenda'];

    $horas_seleccionadas = isset($_POST['horas']) ? $_POST['horas'] : [];
    $hora_personalizada = isset($_POST['hora_nueva']) ? trim($_POST['hora_nueva']) : '';

    if (!empty($hora_personalizada)) {
        if (strlen($hora_personalizada) == 5) {
            $hora_personalizada .= ':00';
        }
        $horas_seleccionadas[] = $hora_personalizada;
    }

    if (empty($horas_seleccionadas) || empty($fecha_seleccionada)) {
        header("Location: ../dashboard.php?msg=vacio");
        exit();
    }

    try {
        $sql = "INSERT IGNORE INTO disponibilidad_barberos (barbero_id, fecha, hora, estado) 
                VALUES (:barbero_id, :fecha, :hora, 'disponible')";
        
        $stmt = $pdo->prepare($sql);

        foreach ($horas_seleccionadas as $hora) {
            $stmt->execute([
                ':barbero_id' => $barbero_id,
                ':fecha' => $fecha_seleccionada,
                ':hora' => $hora
            ]);
        }

        header("Location: ../dashboard.php?msg=horarios_guardados");
        exit();

    } catch (PDOException $e) {
        die("Error al publicar horarios: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>