<?php
session_start();

if (!isset($_SESSION['barbero_id']) || $_SESSION['barbero_rol'] !== 'admin') {
    header("Location: login.html");
    exit();
}

require_once 'backend/conexion.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: admin_dashboard.php");
    exit();
}

// Obtener los datos actuales del barbero
$stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE id = :id AND rol = 'barbero'");
$stmt->execute([':id' => $id]);
$barbero = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barbero) {
    header("Location: admin_dashboard.php?msg=barbero_no_encontrado");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Barbero - BarberControl</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111827] text-slate-100 font-sans min-h-screen flex items-center justify-center p-4">

    <div class="bg-[#1f2937] p-6 md:p-8 rounded-2xl border border-slate-700/50 shadow-2xl w-full max-w-md space-y-6">
        <div class="flex items-center justify-between border-b border-slate-700/50 pb-4">
            <h1 class="text-xl font-bold text-amber-500">✏️ Editar Barbero</h1>
            <a href="admin_dashboard.php" class="text-xs text-slate-400 hover:text-slate-200 transition">← Volver</a>
        </div>

        <form action="backend/gestionar_barberos.php?accion=editar" method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo $barbero['id']; ?>">

            <div>
                <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Nombre Completo</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($barbero['nombre']); ?>" required class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500">
            </div>

            <div>
                <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Correo Electrónico</label>
                <input type="email" name="correo" value="<?php echo htmlspecialchars($barbero['email']); ?>" required class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500">
            </div>

            <div>
                <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Nueva Contraseña (Opcional)</label>
                <div class="relative">
                    <input type="password" id="input-password" name="password" placeholder="Dejar en blanco para mantener la actual" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 pr-10 text-xs text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500">
                    <button type="button" onclick="togglePass()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-amber-500 text-xs select-none">👁️</button>
                </div>
            </div>

            <div class="pt-2 flex space-x-3">
                <a href="admin_dashboard.php" class="w-1/2 text-center bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-2.5 rounded-xl text-xs uppercase tracking-wide transition">Cancelar</a>
                <button type="submit" class="w-1/2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-2.5 rounded-xl text-xs uppercase tracking-wide transition">Guardar Cambios</button>
            </div>
        </form>
    </div>

    <script>
        function togglePass() {
            const pass = document.getElementById('input-password');
            pass.type = pass.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>