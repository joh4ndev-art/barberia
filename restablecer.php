<?php
require_once 'backend/conexion.php';

$token = $_GET['token'] ?? '';
$valido = false;
$barbero_id = null;

if (!empty($token)) {
    try {
        date_default_timezone_set('America/Bogota');
        $ahora = date('Y-m-d H:i:s');

        // 1. Validamos si el token existe en 'recuperacion_pass' y si aún no ha expirado
        $sql = "SELECT correo FROM recuperacion_pass 
                WHERE token = :token AND expira > :ahora 
                ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token, ':ahora' => $ahora]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // 2. Teniendo el correo, buscamos el ID real del barbero en la tabla barberos
            $sqlBarbero = "SELECT id FROM barberos WHERE correo = :correo LIMIT 1";
            $stmtBarbero = $pdo->prepare($sqlBarbero);
            $stmtBarbero->execute([':correo' => $row['correo']]);
            $barbero = $stmtBarbero->fetch(PDO::FETCH_ASSOC);

            if ($barbero) {
                $valido = true;
                $barbero_id = $barbero['id'];
            }
        }
    } catch (PDOException $e) {
        $error = "Error de conexión.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - BarberControl</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111827] text-slate-100 font-sans min-h-screen flex items-center justify-center p-4">

    <div class="bg-[#1f2937] p-8 rounded-2xl border border-slate-700/50 shadow-2xl w-full max-w-md space-y-6">
        <div class="text-center">
            <h2 class="text-2xl font-extrabold text-amber-500">Restablecer Contraseña</h2>
            <p class="text-xs text-slate-400 mt-2">Ingresa tu nueva clave de acceso para actualizar tu cuenta.</p>
        </div>

        <?php if ($valido): ?>
            <form action="backend/actualizar_password.php" method="POST" class="space-y-4">
                <input type="hidden" name="barbero_id" value="<?php echo $barbero_id; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Nueva Contraseña</label>
                    <input type="password" name="nueva_password" required placeholder="••••••••" 
                        class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500">
                </div>

                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-3 rounded-xl text-sm uppercase tracking-wide transition shadow-lg shadow-amber-500/10">
                    Actualizar Contraseña
                </button>
            </form>
        <?php else: ?>
            <div class="bg-rose-500/10 border border-rose-500/20 p-4 rounded-xl text-center space-y-2">
                <p class="text-sm text-rose-400 font-medium">⚠️ El enlace de recuperación no es válido o ya expiró (recuerda que dura solo 1 hora).</p>
                <a href="login.html" class="text-xs text-amber-500 hover:underline block pt-2">Volver al inicio de sesión</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>