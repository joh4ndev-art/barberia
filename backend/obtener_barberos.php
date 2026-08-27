<?php
header('Content-Type: application/json');
require_once 'conexion.php'; 

try {
    // Consultamos la tabla 'usuarios' donde el rol sea 'barbero'
    $query = "SELECT id, nombre, email FROM usuarios WHERE rol = 'barbero' AND estado = 1 ORDER BY id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($barberos);
} catch (PDOException $e) {
    echo json_encode(["error" => "Error al consultar la base de datos: " . $e->getMessage()]);
}
?>