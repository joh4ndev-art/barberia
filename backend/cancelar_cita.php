<?php
session_start();
require_once 'conexion.php';

// Verificación de sesión
if (!isset($_SESSION['barbero_id'])) {
    header("Location: ../login.html");
    exit();
}

if (isset($_GET['id'])) {
    $cita_id = (int)$_GET['id'];
    $mi_id = $_SESSION['barbero_id'];
    $mi_rol = $_SESSION['barbero_rol'] ?? '';
    $es_admin = ($mi_rol === 'jefe' || $mi_rol === 'admin');

    try {
        // 1. OBTENER DATOS DE LA CITA
        // Si es Admin, puede obtener cualquier cita; si es Barbero, solo las suyas.
        if ($es_admin) {
            $sqlInfo = "SELECT barbero_id, fecha, hora FROM citas WHERE id = :id";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([':id' => $cita_id]);
        } else {
            $sqlInfo = "SELECT barbero_id, fecha, hora FROM citas WHERE id = :id AND barbero_id = :barbero_id";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([
                ':id' => $cita_id,
                ':barbero_id' => $mi_id
            ]);
        }
        
        $cita = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if ($cita) {
            $owner_barbero_id = $cita['barbero_id'];

            // 2. ELIMINAR LA CITA
            $sql = "DELETE FROM citas WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $cita_id]);

            // 3. LIBERAR EL HORARIO EN LA TABLA CORRECTA (disponibilidad_barberos)
            $sqlLiberar = "UPDATE disponibilidad_barberos 
                           SET estado = 'disponible' 
                           WHERE barbero_id = :barbero_id 
                             AND fecha = :fecha 
                             AND hora = :hora";
            $stmtLiberar = $pdo->prepare($sqlLiberar);
            $stmtLiberar->execute([
                ':barbero_id' => $owner_barbero_id,
                ':fecha'      => $cita['fecha'],
                ':hora'       => $cita['hora']
            ]);
        }

        header("Location: ../admin_dashboard.php?msg=cita_cancelada");
        exit();

    } catch (PDOException $e) {
        die("Error al cancelar la cita: " . $e->getMessage());
    }
} else {
    header("Location: ../admin_dashboard.php");
    exit();
}
?>