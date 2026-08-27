<?php
session_start();
require_once 'conexion.php';

// Verificación de seguridad básica
if (!isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

if (isset($_GET['id'])) {
    $cita_id = (int)$_GET['id'];
    $barbero_id = $_SESSION['barbero_id'];

    try {
        // 1. OBTENER DATOS DE LA CITA ANTES DE BORRARLA
        // Necesitamos saber la fecha y hora exactas para poder liberar ese espacio
        $sqlInfo = "SELECT fecha, hora FROM citas WHERE id = :id AND barbero_id = :barbero_id";
        $stmtInfo = $pdo->prepare($sqlInfo);
        $stmtInfo->execute([
            ':id' => $cita_id,
            ':barbero_id' => $barbero_id
        ]);
        $cita = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if ($cita) {
            // 2. ELIMINAR LA CITA
            $sql = "DELETE FROM citas WHERE id = :id AND barbero_id = :barbero_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $cita_id,
                ':barbero_id' => $barbero_id
            ]);

            // 3. SINCRONIZACIÓN: VOLVER A PONER EL HORARIO DISPONIBLE
            // Al cambiar el estado a 'disponible', reaparece el botón de la basura 🗑️ en el dashboard
            // y vuelve a estar visible para los clientes en la web
            $sqlLiberar = "UPDATE horarios 
                           SET estado = 'disponible' 
                           WHERE barbero_id = :barbero_id 
                             AND fecha = :fecha 
                             AND hora = :hora";
            $stmtLiberar = $pdo->prepare($sqlLiberar);
            $stmtLiberar->execute([
                ':barbero_id' => $barbero_id,
                ':fecha' => $cita['fecha'],
                ':hora' => $cita['hora']
            ]);
        }

        header("Location: ../dashboard.php?msg=cita_cancelada");
        exit();

    } catch (PDOException $e) {
        die("Error al cancelar la cita: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>