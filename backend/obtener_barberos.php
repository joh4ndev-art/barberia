<?php
header('Content-Type: application/json');
require_once 'conexion.php'; 

try {
    // Añadimos explícitamente el campo 'telefono' en la consulta SELECT
    $query = "SELECT id, nombre, correo, telefono FROM barberos ORDER BY id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enviamos los datos en formato JSON al JS
    echo json_encode($barberos);
} catch (PDOException $e) {
    echo json_encode(["error" => "Error al consultar la base de datos"]);
}
?>