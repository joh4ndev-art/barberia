<?php
session_start();

// Validar sesión
if (!isset($_SESSION['barbero_id'])) {
    header("Location: login.html");
    exit();
}

$mi_id = $_SESSION['barbero_id'];
$mi_nombre = $_SESSION['barbero_nombre'];
$mi_rol = $_SESSION['barbero_rol'];

// CONTROL DE ACCESO: Solo el administrador puede entrar aquí
if (strtolower($mi_rol) !== 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once 'backend/conexion.php';

// Obtener lista de barberos contratados (incluyendo teléfono y normalizando el rol)
try {
    $stmtBG = $pdo->prepare("SELECT id, nombre, email, telefono, estado, rol FROM usuarios WHERE id != :mi_id AND LOWER(rol) = 'barbero'");
    $stmtBG->execute([':mi_id' => $mi_id]);
    $barberosGestion = $stmtBG->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar la gestión de barberos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberControl - Gestión de Barberos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111827] text-slate-100 font-sans min-h-screen relative overflow-x-hidden">

    <!-- BOTÓN FLOTANTE MENÚ -->
    <button 
        type="button" 
        onclick="toggleSidebar()" 
        class="fixed top-4 left-4 z-50 flex items-center gap-2 bg-[#1f2937] border border-slate-700/80 p-2 pr-3 rounded-xl hover:bg-slate-800 transition shadow-2xl cursor-pointer focus:outline-none">
        <div class="w-9 h-9 bg-amber-500 rounded-lg flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-amber-500/20">
            BC
        </div>
        <span class="font-extrabold text-amber-500 text-sm tracking-wide hidden sm:inline">BARBERCONTROL</span>
    </button>

    <!-- BACKDROP -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300"></div>

    <!-- SIDEBAR -->
    <aside id="sidebarMenu" class="fixed top-0 left-0 h-full w-64 bg-[#1f2937] border-r border-slate-700/50 p-6 flex flex-col justify-between z-50 -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl">
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center text-slate-950 font-black text-base shadow-lg shadow-amber-500/20">
                        BC
                    </div>
                    <span class="font-extrabold text-amber-500 tracking-wide text-base">BARBERCONTROL</span>
                </div>
                <button type="button" onclick="toggleSidebar()" class="text-slate-400 hover:text-slate-100 text-xl font-bold p-1">✕</button>
            </div>

            <nav class="flex flex-col gap-2">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-300 hover:bg-slate-800/80 font-bold rounded-xl text-sm transition">
                    <span>📅</span>
                    <span>Mi Agenda</span>
                </a>

                <!-- OPCIÓN SOLO PARA ADMIN -->
                <a href="gestion_barberos.php" class="flex items-center gap-3 px-4 py-3 bg-amber-500 text-slate-950 font-bold rounded-xl text-sm shadow-md transition">
                    <span>👥</span>
                    <span>Gestión Barberos</span>
                </a>

                <a href="mis_ganancias.php" class="flex items-center gap-3 px-4 py-3 text-slate-300 hover:bg-slate-800/80 font-bold rounded-xl text-sm transition">
                    <span>💰</span>
                    <span>Ganancias</span>
                </a>

                <a href="backend/logout.php" class="flex items-center gap-3 px-4 py-3 text-rose-400 hover:bg-rose-500/10 font-bold rounded-xl text-sm transition">
                    <span>🚪</span>
                    <span>Cerrar Sesión</span>
                </a>
            </nav>
        </div>

        <div class="pt-6 border-t border-slate-700/50">
            <p class="text-xs text-slate-500 uppercase tracking-wider">Sesión: <?php echo ucfirst($mi_rol); ?></p>
            <p class="text-sm font-semibold text-slate-300"><?php echo htmlspecialchars($mi_nombre); ?></p>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="w-full min-h-screen p-6 md:p-8 space-y-8 overflow-y-auto">
        
        <!-- ENCABEZADO -->
        <div class="border-b border-slate-700/50 pb-5 pt-2 flex flex-col justify-center pl-48 sm:pl-56 transition-all">
            <h1 class="text-2xl font-extrabold text-slate-100 flex items-center gap-2">
                👥 Gestión de Personal Barberos
            </h1>
            <p class="text-sm text-slate-400">Registra nuevos barberos o administra el personal de la barbería.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- FORMULARIO AGREGAR BARBERO -->
            <section class="bg-[#1f2937] p-6 rounded-2xl border border-slate-700/50 shadow-xl h-fit">
                <h2 class="text-base font-bold text-amber-500 mb-4 flex items-center gap-2">
                    ➕ Agregar Nuevo Barbero
                </h2>
                
                <form action="backend/gestionar_barberos.php?accion=crear" method="POST" class="space-y-4">
                    <div>
                        <input type="text" name="nombre" required placeholder="Nombre Completo" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 focus:outline-none focus:border-amber-500">
                    </div>
                    <div>
                        <input type="email" name="correo" required placeholder="Correo de ingreso" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 focus:outline-none focus:border-amber-500">
                    </div>
                    
                    <!-- CAMPO TELÉFONO / WHATSAPP CON PREFIJO +57 -->
                    <div class="flex rounded-xl overflow-hidden border border-slate-700 focus-within:border-amber-500 transition">
                        <span class="bg-[#18202f] text-slate-400 px-3.5 py-3 text-sm font-bold flex items-center border-r border-slate-700/80 select-none">
                            +57
                        </span>
                        <input 
                            type="tel" 
                            name="telefono" 
                            required 
                            pattern="[0-9]{10}"
                            title="Ingresa un número celular de 10 dígitos (ej. 3001234567)"
                            placeholder="Teléfono / WhatsApp" 
                            class="w-full bg-[#111827] px-4 py-3 text-sm text-slate-100 focus:outline-none placeholder:text-slate-500"
                        >
                    </div>

                    <div>
                        <input type="password" name="password" required placeholder="Contraseña provisoria" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 focus:outline-none focus:border-amber-500">
                    </div>

                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black py-3 rounded-xl text-xs uppercase tracking-wider transition cursor-pointer">
                        CONTRATAR BARBERO
                    </button>
                </form>
            </section>

            <!-- TABLA PLANTILLA DE BARBEROS -->
            <section class="lg:col-span-2 bg-[#1f2937] p-6 rounded-2xl border border-slate-700/50 shadow-xl">
                <h2 class="text-base font-bold text-amber-500 mb-4 flex items-center gap-2">
                    👥 Plantilla de Barberos
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-[#111827] text-xs uppercase text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th class="p-4">NOMBRE</th>
                                <th class="p-4">CORREO</th>
                                <th class="p-4">TELÉFONO</th>
                                <th class="p-4">ROL</th>
                                <th class="p-4 text-right">ACCIÓN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php if (!empty($barberosGestion)): ?>
                                <?php foreach ($barberosGestion as $b): ?>
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="p-4 font-bold text-slate-100"><?php echo htmlspecialchars($b['nombre']); ?></td>
                                        <td class="p-4 text-slate-400"><?php echo htmlspecialchars($b['email']); ?></td>
                                        <td class="p-4 text-slate-300 font-mono text-xs">
                                            <?php echo !empty($b['telefono']) ? htmlspecialchars($b['telefono']) : '<span class="text-slate-600">Sin registrar</span>'; ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-slate-800 text-slate-300 px-2.5 py-1 rounded-lg border border-slate-700 text-xs font-bold uppercase">
                                                <?php echo htmlspecialchars($b['rol']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- BOTÓN EDITAR -->
                                                <a href="editar_barbero.php?id=<?php echo $b['id']; ?>" class="bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 px-3 py-1.5 rounded-xl text-xs font-bold transition inline-block">
                                                    Editar
                                                </a>

                                                <!-- BOTÓN DESPEDIR -->
                                                <form action="backend/gestionar_barberos.php?accion=eliminar" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas despedir/eliminar a <?php echo htmlspecialchars(addslashes($b['nombre'])); ?>?');" class="inline">
                                                    <input type="hidden" name="barbero_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" class="bg-rose-500/10 text-rose-400 border border-rose-500/30 hover:bg-rose-500/20 px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer">
                                                        Despedir
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-slate-500">
                                        No hay barberos registrados actualmente.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarMenu');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar && backdrop) {
                sidebar.classList.toggle('-translate-x-full');
                backdrop.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>