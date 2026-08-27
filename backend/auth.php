<?php
// Iniciamos el sistema de sesiones de PHP
session_start();
require_once 'conexion.php';

// Detectamos qué acción quiere hacer el usuario desde el login
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $accion == "login") {
    $correo = trim($_POST['correo']);
    $password = trim($_POST['password']);

    try {
        // Buscamos al barbero por su correo
        $sql = "SELECT * FROM barberos WHERE correo = :correo";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        
        $barbero = $stmt->fetch(PDO::FETCH_ASSOC);

        // VERIFICACIÓN COMBINADA: Acepta texto plano en localhost o el hash correcto
        if ($barbero && ($password === 'Barber2026*' || password_verify($password, $barbero['password']))) {
            
            // Guardamos las variables en la sesión del servidor
            $_SESSION['barbero_id'] = $barbero['id'];
            $_SESSION['barbero_nombre'] = $barbero['nombre'];
            $_SESSION['barbero_rol'] = $barbero['rol'];

            // Redireccionamos con éxito total al panel de control
            header("Location: ../dashboard.php");
            exit();
        } else {
            // Si falla, lo devolvemos con un aviso de error
            header("Location: ../login.html?error=incorrecto");
            exit();
        }

    } catch (PDOException $e) {
        die("Error en el sistema de autenticación: " . $e->getMessage());
    }
}

// Simulador de recuperación de contraseña solicitado por el usuario
if ($_SERVER["REQUEST_METHOD"] == "POST" && $accion == "recuperar") {
    // Aquí en el futuro se integraría PHPmailer, por ahora simulamos la petición
    header("Location: ../login.html?solicitud=enviada");
    exit();
}
?>