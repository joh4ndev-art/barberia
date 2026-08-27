<?php
session_start();
session_unset(); // Limpia las variables de sesión
session_destroy(); // Destruye la sesión en el servidor

// Lo mandamos directo al inicio de la barbería
header("Location: ../index.html");
exit();
?>