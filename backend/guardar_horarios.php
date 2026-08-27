<?php
session_start();
require_once 'conexion.php';

// Verificamos que el usuario tenga sesión activa
if (!isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $barbero_id = $_SESSION['barbero_id'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];

    try {
        // 1. VALIDACIÓN ANTI-DUPLICADOS: Evita que el mismo barbero publique la misma hora dos veces
        $sqlCheck = "SELECT COUNT(*) FROM horarios WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':barbero_id' => $barbero_id,
            ':fecha' => $fecha,
            ':hora' => $hora
        ]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            // Si ya existe, regresamos al dashboard con un aviso
            header("Location: ../dashboard.php?msg=horario_duplicado");
            exit();
        }

        // 2. INSERCIÓN: Si no está duplicado, se guarda en la base de datos
        $sql = "INSERT INTO horarios (barbero_id, fecha, hora, estado) VALUES (:barbero_id, :fecha, :hora, 'disponible')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':barbero_id' => $barbero_id,
            ':fecha' => $fecha,
            ':hora' => $hora
        ]);

        header("Location: ../dashboard.php?msg=horarios_guardados");
        exit();

    } catch (PDOException $e) {
        die("Error al guardar el horario: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>