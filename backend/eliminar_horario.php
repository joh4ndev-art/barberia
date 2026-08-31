<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

if (isset($_GET['id'])) {
    $horario_id = (int)$_GET['id'];
    $barbero_id = $_SESSION['barbero_id'];

    try {
        // Se elimina directamente de la tabla disponibilidad_barberos solo si está 'disponible'
        $sql = "DELETE FROM disponibilidad_barberos 
                WHERE id = :id AND barbero_id = :barbero_id AND estado = 'disponible'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $horario_id,
            ':barbero_id' => $barbero_id
        ]);

        header("Location: ../admin_dashboard.php?msg=horario_eliminado");
        exit();

    } catch (PDOException $e) {
        die("Error al remover el bloque de horario: " . $e->getMessage());
    }
} else {
    header("Location: ../admin_dashboard.php");
    exit();
}
?>