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
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($accion === "crear" || empty($accion))) {
    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? $_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($nombre) || empty($correo) || empty($password)) {
        header("Location: ../gestion_barberos.php?error=campos_vacios");
        exit();
    }

    // Formatear teléfono con el prefijo +57 si no lo tiene
    if (!empty($telefono) && strpos($telefono, '+57') !== 0) {
        $telefono = '+57' . $telefono;
    }

    $passwordCifrada = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Validar que el correo no esté duplicado
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmtCheck->execute([':email' => $correo]);
        if ($stmtCheck->fetch()) {
            header("Location: ../gestion_barberos.php?error=correo_existente");
            exit();
        }

        // Se inserta rol en minúsculas ('barbero') para asegurar compatibilidad con la consulta SELECT
        $sql = "INSERT INTO usuarios (nombre, email, telefono, password, rol, estado) 
                VALUES (:nombre, :email, :telefono, :password, 'barbero', 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $correo,
            ':telefono' => $telefono,
            ':password' => $passwordCifrada
        ]);

        header("Location: ../gestion_barberos.php?msg=creado");
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
    $correo   = trim($_POST['correo'] ?? $_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($id <= 0 || empty($nombre) || empty($correo)) {
        header("Location: ../gestion_barberos.php?msg=campos_vacios");
        exit();
    }

    // Formatear teléfono con +57
    if (!empty($telefono) && strpos($telefono, '+57') !== 0) {
        $telefono = '+57' . $telefono;
    }

    try {
        // Si ingresó contraseña nueva la actualizamos; si no, conservamos la actual
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono, password = :pass WHERE id = :id AND LOWER(rol) = 'barbero'";
            $params = [
                ':nombre'   => $nombre,
                ':email'    => $correo,
                ':telefono' => $telefono,
                ':pass'     => $passHash,
                ':id'       => $id
            ];
        } else {
            $sql = "UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :id AND LOWER(rol) = 'barbero'";
            $params = [
                ':nombre'   => $nombre,
                ':email'    => $correo,
                ':telefono' => $telefono,
                ':id'       => $id
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ../gestion_barberos.php?msg=actualizado");
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar barbero: " . $e->getMessage());
    }
}

// ---------------------------------------------------------
// ACCIÓN: ELIMINAR BARBERO Y LIMPIAR DEPENDENCIAS
// ---------------------------------------------------------
if ($accion === "eliminar" && (isset($_GET['id']) || isset($_POST['barbero_id']))) {
    $id_eliminar = (int)($_POST['barbero_id'] ?? $_GET['id']);

    try {
        $pdo->beginTransaction();

        // 1. Borrar citas vinculadas al barbero
        $stmtCitas = $pdo->prepare("DELETE FROM citas WHERE barbero_id = :id");
        $stmtCitas->execute([':id' => $id_eliminar]);

        // 2. Borrar horarios de disponibilidad del barbero
        $stmtDispo = $pdo->prepare("DELETE FROM disponibilidad_barberos WHERE barbero_id = :id");
        $stmtDispo->execute([':id' => $id_eliminar]);

        // 3. Eliminar al usuario de la tabla usuarios
        $stmtUsuario = $pdo->prepare("DELETE FROM usuarios WHERE id = :id AND LOWER(rol) = 'barbero'");
        $stmtUsuario->execute([':id' => $id_eliminar]);

        $pdo->commit();

        header("Location: ../gestion_barberos.php?msg=eliminado");
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al eliminar barbero: " . $e->getMessage());
    }
}

// Redirección por defecto
header("Location: ../gestion_barberos.php");
exit();
?>