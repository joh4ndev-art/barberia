<?php
session_start();
require_once 'conexion.php';

// Validamos que solo el jefe pueda disparar este archivo (Blindado con strtolower)
if (!isset($_SESSION['barbero_id']) || !isset($_SESSION['barbero_rol']) || strtolower(trim($_SESSION['barbero_rol'])) !== 'jefe') {
    die("Acceso denegado.");
}

$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// ACCIÓN: CONTRATAR NUEVO BARBERO
if ($_SERVER["REQUEST_METHOD"] == "POST" && $accion == "crear") {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']); // <-- CAPTURAMOS EL NUEVO CAMPO DE WHATSAPP
    $password = trim($_POST['password']);
    
    // Ciframos la contraseña con el algoritmo seguro por defecto de PHP
    $passwordCifrada = password_hash($password, PASSWORD_DEFAULT);

    try {
        // MODIFICAMOS EL SQL PARA INCLUIR LA COLUMNA TELEFONO
        $sql = "INSERT INTO barberos (nombre, correo, telefono, password, rol) VALUES (:nombre, :correo, :telefono, :password, 'barbero')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':telefono', $telefono); // <-- VINCULAMOS EL TELÉFONO
        $stmt->bindParam(':password', $passwordCifrada);
        $stmt->execute();

        header("Location: ../dashboard.php?msg=creado");
        exit();
    } catch (PDOException $e) {
        die("Error al registrar barbero: " . $e->getMessage());
    }
}

// ACCIÓN: ELIMINAR BARBERO (Punto Corregido de Integridad y Llaves Foráneas)
if ($accion == "eliminar" && isset($_GET['id'])) {
    $id_eliminar = (int)$_GET['id'];

    try {
        // PASO PREVIO OBLIGATORIO: Borramos primero las citas vinculadas a este barbero
        $sqlCitas = "DELETE FROM citas WHERE barbero_id = :id";
        $stmtCitas = $pdo->prepare($sqlCitas);
        $stmtCitas->bindParam(':id', $id_eliminar);
        $stmtCitas->execute();

        // PASO FINAL: Ahora sí removemos al barbero de la plantilla
        $sqlBarbero = "DELETE FROM barberos WHERE id = :id";
        $stmtBarbero = $pdo->prepare($sqlBarbero);
        $stmtBarbero->bindParam(':id', $id_eliminar);
        $stmtBarbero->execute();

        header("Location: ../dashboard.php?msg=eliminado");
        exit();
    } catch (PDOException $e) {
        die("Error al eliminar barbero: " . $e->getMessage());
    }
}
?>