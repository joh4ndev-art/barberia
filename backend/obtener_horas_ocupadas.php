<?php
require_once 'conexion.php';
header('Content-Type: application/json');

date_default_timezone_set('America/Bogota'); 

if (isset($_GET['barbero_id']) && isset($_GET['fecha'])) {
    $barbero_id = (int)trim($_GET['barbero_id']);
    $fecha = trim($_GET['fecha']);

    try {
        // 1. Buscamos las horas que el barbero habilitó y cuyo estado es estrictamente 'disponible'
        // Y además, por seguridad, verificamos que NO existan en la tabla citas
        $sqlDispo = "SELECT TIME_FORMAT(h.hora, '%H:%i:%s') as hora 
                     FROM horarios h
                     WHERE h.barbero_id = :barbero_id 
                       AND h.fecha = :fecha 
                       AND h.estado = 'disponible'
                       AND NOT EXISTS (
                           SELECT 1 FROM citas c 
                           WHERE c.barbero_id = h.barbero_id 
                             AND c.fecha = h.fecha 
                             AND c.hora = h.hora 
                             AND c.estado = 'pendiente'
                       )";
        $stmtD = $pdo->prepare($sqlDispo);
        $stmtD->execute([':barbero_id' => $barbero_id, ':fecha' => $fecha]);
        $turnosHabilitados = $stmtD->fetchAll(PDO::FETCH_COLUMN);

        // 2. Buscamos cuáles horas están ocupadas en el horario O ya tienen cita registrada
        $sqlCitas = "SELECT TIME_FORMAT(hora, '%H:%i:%s') as hora 
                     FROM horarios 
                     WHERE barbero_id = :barbero_id AND fecha = :fecha AND estado = 'ocupado'";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([':barbero_id' => $barbero_id, ':fecha' => $fecha]);
        $turnosOcupados = $stmtC->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'habilitados' => $turnosHabilitados ? $turnosHabilitados : [],
            'ocupados' => $turnosOcupados ? $turnosOcupados : []
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage(), 'habilitados' => [], 'ocupados' => []]);
    }
} else {
    echo json_encode(['habilitados' => [], 'ocupados' => []]);
}
?>