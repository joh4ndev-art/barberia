<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cita_id = intval($_POST['cita_id'] ?? 0);
    $monto = floatval($_POST['monto'] ?? 0);
    $servicio = trim($_POST['servicio_nombre'] ?? '');
    $barbero_id = $_SESSION['usuario_id'];

    if ($cita_id > 0 && $monto >= 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE citas 
                SET estado = 'finalizada', 
                    precio = :monto, 
                    servicio = :servicio 
                WHERE id = :id AND barbero_id = :barbero_id
            ");
            
            $stmt->execute([
                ':monto' => $monto,
                ':servicio' => $servicio,
                ':id' => $cita_id,
                ':barbero_id' => $barbero_id
            ]);

            $_SESSION['mensaje'] = "Cita finalizada con éxito.";
        } catch (\PDOException $e) {
            die("Error al guardar: " . $e->getMessage());
        }
    }
}

header("Location: ../admin_dashboard.php");
exit();