<?php
// Llamamos al puente de conexión
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Capturamos los datos enviados por el HTML
    $nombre = trim($_POST['nombre_cliente'] ?? '');
    
    // Evitamos duplicar el prefijo 57 si por alguna razón ya se envió en el input
    $telInput = trim($_POST['telefono'] ?? '');
    $telefono = (strpos($telInput, '57') === 0) ? $telInput : '57' . $telInput;
    
    $fecha = trim($_POST['fecha'] ?? '');
    
    // CORRECCIÓN CLAVE: Cambiado de 'hora_cita' a 'hora' para sincronizar con index.html
    $hora = trim($_POST['hora'] ?? ''); 
    $barbero_id = trim($_POST['barbero_id'] ?? '');

    // VALIDACIÓN EXTREMA: Asegurarse de que no vayan campos vacíos
    if (empty($nombre) || empty($telInput) || empty($fecha) || empty($hora) || empty($barbero_id)) {
        header("Location: ../index.html?error=campos_vacios");
        exit();
    }

    try {
        // --- PASO 1: VALIDACIÓN ANTI-DUPLICADOS (Punto 4) ---
        // Buscamos si ya existe una cita activa para ese mismo barbero, fecha y hora
        $sqlValidar = "SELECT COUNT(*) FROM citas 
                       WHERE barbero_id = :barbero_id 
                       AND fecha = :fecha 
                       AND hora = :hora 
                       AND estado = 'pendiente'";
        
        $stmtValidar = $pdo->prepare($sqlValidar);
        $stmtValidar->bindParam(':barbero_id', $barbero_id);
        $stmtValidar->bindParam(':fecha', $fecha);
        $stmtValidar->bindParam(':hora', $hora);
        $stmtValidar->execute();
        
        // Obtenemos el número de filas encontradas
        $existeCita = $stmtValidar->fetchColumn();

        if ($existeCita > 0) {
            // Si ya existe, detenemos el proceso y devolvemos un mensaje de error por la URL
            header("Location: ../index.html?estado=ocupado");
            exit();
        }

        // --- PASO 2: SI ESTÁ LIBRE, PROCEDEMOS A GUARDAR ---
        $sql = "INSERT INTO citas (nombre_cliente, telefono, barbero_id, fecha, hora, estado) 
                VALUES (:nombre, :telefono, :barbero_id, :fecha, :hora, 'pendiente')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':barbero_id', $barbero_id);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        
        $stmt->execute();
        
        // --- PASO 3: ACTUALIZACIÓN AUTOMÁTICA DEL ESTADO EN HORARIOS ---
        // Sincroniza la parrilla horaria para bloquear el cupo y actualizar el Dashboard
        $sqlUpdateHorario = "UPDATE horarios 
                             SET estado = 'ocupado' 
                             WHERE barbero_id = :barbero_id 
                               AND fecha = :fecha 
                               AND hora = :hora";
        $stmtUpdate = $pdo->prepare($sqlUpdateHorario);
        $stmtUpdate->bindParam(':barbero_id', $barbero_id);
        $stmtUpdate->bindParam(':fecha', $fecha);
        $stmtUpdate->bindParam(':hora', $hora);
        $stmtUpdate->execute();
        
        // Éxito total: Redirige y activa el modal premium en el cliente
        header("Location: ../index.html?estado=exito");
        exit();

    } catch(PDOException $e) {
        die("Ocurrió un error al procesar la cita: " . $e->getMessage());
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>