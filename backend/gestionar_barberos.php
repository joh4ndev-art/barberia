<?php
session_start();
require_once 'conexion.php';

// Validar que solo el Jefe o Admin pueda ejecutar este script
$rol_actual = strtolower(trim($_SESSION['barbero_rol'] ?? ''));
if (!isset($_SESSION['barbero_id']) || ($rol_actual !== 'jefe' && $rol_actual !== 'admin')) {
    die("Acceso denegado.");
}

$accion = $_GET['accion'] ?? '';

// ACCIÓN: REGISTRAR NUEVO BARBERO
if ($_SERVER["REQUEST_METHOD"] === "POST" && $accion === "crear") {
    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? $_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($nombre) || empty($correo) || empty($password)) {
        die("Por favor completa los campos obligatorios.");
    }

    $passwordCifrada = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Inserción en la tabla correcta 'usuarios' usando la columna 'email'
        $sql = "INSERT INTO usuarios (nombre, email, telefono, password, rol, estado) 
                VALUES (:nombre, :email, :telefono, :password, 'barbero', 'activo')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $correo,
            ':telefono' => $telefono,
            ':password' => $passwordCifrada
        ]);

        header("Location: ../admin_dashboard.php?msg=creado");
        exit();
    } catch (PDOException $e) {
        die("Error al registrar barbero: " . $e->getMessage());
    }
}

// ACCIÓN: ELIMINAR BARBERO Y LIMPIAR DEPENDENCIAS
if ($accion === "eliminar" && isset($_GET['id'])) {
    $id_eliminar = (int)$_GET['id'];

    try {
        $pdo->beginTransaction();

        // 1. Borrar citas vinculadas al barbero
        $stmtCitas = $pdo->prepare("DELETE FROM citas WHERE barbero_id = :id");
        $stmtCitas->execute([':id' => $id_eliminar]);

        // 2. Borrar horarios de disponibilidad del barbero
        $stmtDispo = $pdo->prepare("DELETE FROM disponibilidad_barberos WHERE barbero_id = :id");
        $stmtDispo->execute([':id' => $id_eliminar]);

        // 3. Eliminar al usuario de la tabla usuarios
        $stmtUsuario = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND rol = 'barbero'");
        $stmtUsuario->execute([':id' => $id_eliminar]);

        $pdo->commit();

        header("Location: ../admin_dashboard.php?msg=eliminado");
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al eliminar barbero: " . $e->getMessage());
    }
} else {
    header("Location: ../admin_dashboard.php");
    exit();
}
?>