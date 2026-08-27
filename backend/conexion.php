<?php
$host = 'localhost'; // En Hostinger se deja 'localhost' siempre
$db   = 'u185582191_BarberiaJT'; // <-- PON EL NOMBRE COMPLETO DE TU BD DE HOSTINGER
$user = 'u185582191_BarberTyler';    // <-- PON EL USUARIO COMPLETO DE TU BD DE HOSTINGER
$pass = 'BarberT_yl3r99';  // <-- PON LA CONTRASEÑA DE TU BD DE HOSTINGER
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
    die("Error crítico de conexión a la base de datos: " . $e->getMessage());
}
?>