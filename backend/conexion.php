<?php
// Configuración para el entorno de desarrollo local (XAMPP en Linux Mint)
$host = 'localhost';
$db   = 'barberia_db'; // Nombre de tu base de datos local
$user = 'root';        // Usuario por defecto en XAMPP
$pass = '';            // En XAMPP para Linux la contraseña de root viene vacía por defecto
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error crítico de conexión a la base de datos local: " . $e->getMessage());
}
?>