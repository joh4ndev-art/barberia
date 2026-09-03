<?php
// backend/procesar_cancelacion.php
session_start();
header('Content-Type: application/json');

// Si no hay sesión iniciada, se devuelve error de autenticación
if (!isset($_SESSION['barbero_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no autorizada.']);
    exit();
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cita_id = isset($_POST['cita_id']) ? intval($_POST['cita_id']) : 0;
    $accion  = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($cita_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de cita no válido.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Consultar la cita para obtener su fecha, hora y barbero asignado
        $stmtCita = $pdo->prepare("SELECT fecha, hora, barbero_id FROM citas WHERE id = :id");
        $stmtCita->execute([':id' => $cita_id]);
        $cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

        if (!$cita) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'La cita no existe.']);
            exit();
        }

        // 2. Eliminar la cita confirmada de la tabla 'citas'
        $stmtDelete = $pdo->prepare("DELETE FROM citas WHERE id = :id");
        $stmtDelete->execute([':id' => $cita_id]);

        // 3. Según la acción, actualizar o liberar la disponibilidad del barbero:
        if ($accion === 'cancelar') {
            // Si es CANCELAR: Retirar por completo el bloque disponible del horario público
            $stmtDisp = $pdo->prepare("DELETE FROM disponibilidad_barberos WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora");
            $stmtDisp->execute([
                ':barbero_id' => $cita['barbero_id'],
                ':fecha'      => $cita['fecha'],
                ':hora'       => $cita['hora']
            ]);
        } elseif ($accion === 'reubicar') {
            // Si es REUBICAR: Volver a marcar el bloque como 'disponible' para que otro cliente o el mismo lo reserve
            $stmtDisp = $pdo->prepare("UPDATE disponibilidad_barberos SET estado = 'disponible' WHERE barbero_id = :barbero_id AND fecha = :fecha AND hora = :hora");
            $stmtDisp->execute([
                ':barbero_id' => $cita['barbero_id'],
                ':fecha'      => $cita['fecha'],
                ':hora'       => $cita['hora']
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true]);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método de solicitud no válido.']);
    exit();
}