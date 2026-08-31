<?php
session_start();
require_once 'conexion.php';

// Validar que solo un Administrador pueda ejecutar este script
$rol_actual = strtolower(trim($_SESSION['barbero_rol'] ?? ''));
if (!isset($_SESSION['barbero_id']) || $rol_actual !== 'admin') {
    die("Acceso denegado.");
}

$accion = $_GET['accion'] ?? '';

// ---------------------------------------------------------
// ACCIÓN: REGISTRAR NUEVO BARBERO
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $accion === "crear") {
    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? $_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($nombre) || empty($correo) || empty($password)) {
        die("Por favor completa los campos obligatorios.");
    }

    $passwordCifrada = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Se envía 1 como entero para el campo estado (1 = activo, 0 = inactivo)
        $sql = "INSERT INTO usuarios (nombre, email, password, rol, estado) 
                VALUES (:nombre, :email, :password, 'barbero', 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $correo,
            ':password' => $passwordCifrada
        ]);

        header("Location: ../admin_dashboard.php?msg=creado");
        exit();
    } catch (PDOException $e) {
        die("Error al registrar barbero: " . $e->getMessage());
    }
}

// ---------------------------------------------------------
// ACCIÓN: EDITAR BARBERO
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && $accion === "editar") {
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($id <= 0 || empty($nombre) || empty($correo)) {
        header("Location: ../admin_dashboard.php?msg=campos_vacios");
        exit();
    }

    try {
        // Si ingresó una contraseña nueva la hasheamos, si no, mantenemos la actual
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, password = :pass WHERE id = :id AND rol = 'barbero'";
            $params = [
                ':nombre' => $nombre,
                ':email'  => $correo,
                ':pass'    => $passHash,
                ':id'      => $id
            ];
        } else {
            $sql = "UPDATE usuarios SET nombre = :nombre, email = :email WHERE id = :id AND rol = 'barbero'";
            $params = [
                ':nombre' => $nombre,
                ':email'  => $correo,
                ':id'      => $id
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../admin_dashboard.php?msg=actualizado");
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar barbero: " . $e->getMessage());
    }
}

// ---------------------------------------------------------
// ACCIÓN: ELIMINAR BARBERO Y LIMPIAR DEPENDENCIAS
// ---------------------------------------------------------
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
}

// Si la acción no coincide con ninguna opción válida
header("Location: ../admin_dashboard.php");
exit();
?>