<?php
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $barbero_id = (int)$_POST['barbero_id'];
    $token = trim($_POST['token']);
    $nueva_password = $_POST['nueva_password'];

    if (empty($nueva_password) || empty($token)) {
        die("Datos inválidos.");
    }

    try {
        // Encriptamos la contraseña con el algoritmo seguro nativo de PHP
        $password_hashed = password_hash($nueva_password, PASSWORD_BCRYPT);

        // 1. Actualizamos la contraseña del barbero
        $sql = "UPDATE barberos SET password = :password WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':password' => $password_hashed,
            ':id' => $barbero_id
        ]);

        // 2. Por seguridad, eliminamos el token usado de la tabla sincronizada 'recuperacion_pass'
        $sqlDelete = "DELETE FROM recuperacion_pass WHERE token = :token";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([':token' => $token]);

        // Redirigimos al login avisando del éxito
        header("Location: ../login.html?status=password_cambiada");
        exit();

    } catch (PDOException $e) {
        die("Error al actualizar la contraseña: " . $e->getMessage());
    }
} else {
    header("Location: ../login.html");
    exit();
}
?>