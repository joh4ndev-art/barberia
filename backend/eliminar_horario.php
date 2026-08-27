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
        // SEGURIDAD: Solo se puede eliminar un horario si pertenece al barbero logueado
        // y si el estado es 'disponible' (evita borrar horas que ya pagaron o reservaron clientes)
        $sql = "DELETE FROM horarios WHERE id = :id AND barbero_id = :barbero_id AND estado = 'disponible'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $horario_id,
            ':barbero_id' => $barbero_id
        ]);

        header("Location: ../dashboard.php?msg=horario_eliminado");
        exit();

    } catch (PDOException $e) {
        die("Error al remover el bloque de horario: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>