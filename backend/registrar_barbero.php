<?php
session_start();

// 1. Validar que la sesión exista y sea de un usuario Administrador
if (!isset($_SESSION['barbero_id']) || $_SESSION['barbero_rol'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

// 2. Verificar que la petición sea por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../gestion_barberos.php");
    exit();
}

require_once 'conexion.php';

// 3. Capturar y limpiar los datos enviados por el formulario
$nombre   = trim($_POST['nombre'] ?? '');
$email    = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$password = trim($_POST['password'] ?? '');

// 4. Validar campos obligatorios
if (empty($nombre) || empty($email) || empty($telefono) || empty($password)) {
    header("Location: ../gestion_barberos.php?error=campos_vacios");
    exit();
}

// 5. Formatear el teléfono adjuntándole el prefijo +57 si no lo incluye
if (strpos($telefono, '+57') !== 0) {
    $telefono = '+57' . $telefono;
}

try {
    // 6. Validar que el correo no esté registrado previamente
    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
    $stmtCheck->execute([':email' => $email]);

    if ($stmtCheck->fetch()) {
        header("Location: ../gestion_barberos.php?error=correo_existente");
        exit();
    }

    // 7. Encriptar la contraseña provisoria
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // 8. Insertar el nuevo barbero en la tabla usuarios con rol 'barbero'
    $stmtInsert = $pdo->prepare("INSERT INTO usuarios (nombre, email, telefono, password, rol, estado) VALUES (:nombre, :email, :telefono, :password, 'barbero', 'activo')");
    $stmtInsert->execute([
        ':nombre'   => $nombre,
        ':email'    => $email,
        ':telefono' => $telefono,
        ':password' => $passwordHash
    ]);

    // Redirección exitosa
    header("Location: ../gestion_barberos.php?msg=barbero_registrado");
    exit();

} catch (PDOException $e) {
    // Manejo de errores
    error_log("Error al registrar barbero: " . $e->getMessage());
    header("Location: ../gestion_barberos.php?error=db_error");
    exit();
}