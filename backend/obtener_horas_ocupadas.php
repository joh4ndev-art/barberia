<?php
require_once 'conexion.php';
header('Content-Type: application/json');

date_default_timezone_set('America/Bogota'); 

if (isset($_GET['barbero_id']) && isset($_GET['fecha'])) {
    $barbero_id = (int)trim($_GET['barbero_id']);
    $fecha = trim($_GET['fecha']);

    try {
        // 1. Buscamos las horas habilitadas con estado 'disponible' en la tabla correcta
        // y verificamos que no exista una cita pendiente asociada
        $sqlDispo = "SELECT TIME_FORMAT(d.hora, '%H:%i:%s') as hora 
                     FROM disponibilidad_barberos d
                     WHERE d.barbero_id = :barbero_id 
                       AND d.fecha = :fecha 
                       AND d.estado = 'disponible'
                       AND NOT EXISTS (
                           SELECT 1 FROM citas c 
                           WHERE c.barbero_id = d.barbero_id 
                             AND c.fecha = d.fecha 
                             AND c.hora = d.hora 
                             AND c.estado = 'pendiente'
                       )";
        $stmtD = $pdo->prepare($sqlDispo);
        $stmtD->execute([':barbero_id' => $barbero_id, ':fecha' => $fecha]);
        $turnosHabilitados = $stmtD->fetchAll(PDO::FETCH_COLUMN);

        // 2. Buscamos las horas marcadas como 'ocupado' en disponibilidad_barberos
        $sqlCitas = "SELECT TIME_FORMAT(hora, '%H:%i:%s') as hora 
                     FROM disponibilidad_barberos 
                     WHERE barbero_id = :barbero_id AND fecha = :fecha AND estado = 'ocupado'";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([':barbero_id' => $barbero_id, ':fecha' => $fecha]);
        $turnosOcupados = $stmtC->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'habilitados' => $turnosHabilitados ? $turnosHabilitados : [],
            'ocupados'    => $turnosOcupados ? $turnosOcupados : []
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage(), 'habilitados' => [], 'ocupados' => []]);
    }
} else {
    echo json_encode(['habilitados' => [], 'ocupados' => []]);
}
?>