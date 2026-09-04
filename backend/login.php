<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: ../login.html?error=campos_vacios");
        exit();
    }

    try {
        $sql = "SELECT id, nombre, email, password, rol, estado FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            
            if ((int)$usuario['estado'] !== 1) {
                die("Tu cuenta se encuentra inactiva. Contacta al administrador.");
            }

            // Guardamos la sesión estandarizada para todo el sistema
            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_rol']    = $usuario['rol'];

            // Variables secundarias por compatibilidad
            $_SESSION['barbero_id']     = $usuario['id'];
            $_SESSION['barbero_nombre'] = $usuario['nombre'];
            $_SESSION['barbero_rol']    = $usuario['rol'];
            $_SESSION['nombre']         = $usuario['nombre'];
            $_SESSION['rol']            = $usuario['rol'];

            // Redirección según el rol
            $rol_normalizado = strtolower(trim($usuario['rol']));
            if ($rol_normalizado === 'admin' || $rol_normalizado === 'barbero' || $rol_normalizado === 'administrador') {
                header("Location: ../admin_dashboard.php");
            } else {
                header("Location: ../dashboard.php");
            }
            exit();

        } else {
            header("Location: ../login.html?error=credenciales_incorrectas");
            exit();
        }

    } catch (PDOException $e) {
        die("Error en la autenticación: " . $e->getMessage());
    }
} else {
    header("Location: ../login.html");
    exit();
}
?>