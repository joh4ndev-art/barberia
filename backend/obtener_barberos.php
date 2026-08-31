<?php
header('Content-Type: application/json');
require_once 'conexion.php'; 

try {
    // Incluye tanto barberos como administradores activos para permitir reservas con el Administrador
    $query = "SELECT id, nombre, email, rol 
              FROM usuarios 
              WHERE (rol = 'barbero' OR rol = 'admin' OR rol = 'jefe') 
                AND (estado = 'activo' OR estado = 1 OR estado IS NULL) 
              ORDER BY id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($barberos);
} catch (PDOException $e) {
    echo json_encode(["error" => "Error al consultar la base de datos: " . $e->getMessage()]);
}
?>