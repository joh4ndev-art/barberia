<?php
// 1. Zona horaria en PHP
date_default_timezone_set('America/Bogota'); // Ajusta según tu ubicación local

$host = 'localhost';
$db   = 'barberia_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 2. Sincronizar zona horaria en MySQL
    $pdo->exec("SET time_zone = '-05:00';"); // Offset de tu zona horaria local
    
} catch (\PDOException $e) {
    die("Error crítico de conexión a la base de datos local: " . $e->getMessage());
}
?>