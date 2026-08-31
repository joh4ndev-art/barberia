<?php
session_start();

// Si no hay sesión iniciada, se expulsa al usuario al login
if (!isset($_SESSION['barbero_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'backend/conexion.php';

$mi_id = $_SESSION['barbero_id'];
$mi_nombre = $_SESSION['barbero_nombre'];
$mi_rol = $_SESSION['barbero_rol'];

// Configuración de zona horaria
date_default_timezone_set('America/Bogota'); 
$fecha_hoy = date('Y-m-d');
$fecha_manana = date('Y-m-d', strtotime('+1 day'));

// Exclusividad de rol Admin
$es_admin = ($mi_rol === 'admin');

try {
    // 1. LISTA DE BARBEROS (Para el selector del administrador)
    $barberosLista = [];
    if ($es_admin) {
        $stmtB = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'barbero'");
        $stmtB->execute();
        $barberosLista = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. FILTRAR CITAS (Hoy y Mañana)
    $barbero_seleccionado = isset($_GET['barbero_ver']) && $_GET['barbero_ver'] !== '' ? intval($_GET['barbero_ver']) : null;

    if ($es_admin && $barbero_seleccionado) {
        // Citas del barbero específico seleccionado por el admin (Hoy y Mañana)
        $sqlCitas = "SELECT c.id, c.fecha, cl.nombre AS nombre_cliente, cl.telefono, TIME_FORMAT(c.hora, '%H:%i') as hora_corta, u.nombre AS nombre_barbero 
                   FROM citas c 
                   JOIN usuarios u ON c.barbero_id = u.id 
                   JOIN clientes cl ON c.cliente_id = cl.id 
                   WHERE c.barbero_id = :barbero_id 
                     AND c.fecha BETWEEN :fecha_hoy AND :fecha_manana 
                     AND c.estado = 'pendiente'
                   ORDER BY c.fecha ASC, c.hora ASC";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([
            ':barbero_id'   => $barbero_seleccionado,
            ':fecha_hoy'    => $fecha_hoy,
            ':fecha_manana' => $fecha_manana
        ]);
    } else {
        // Muestra ÚNICAMENTE las citas propias (Si entra el admin sin filtrar, muestra solo las del Admin)
        $sqlCitas = "SELECT c.id, c.fecha, cl.nombre AS nombre_cliente, cl.telefono, TIME_FORMAT(c.hora, '%H:%i') as hora_corta, u.nombre AS nombre_barbero 
                   FROM citas c 
                   JOIN usuarios u ON c.barbero_id = u.id 
                   JOIN clientes cl ON c.cliente_id = cl.id 
                   WHERE c.barbero_id = :barbero_id 
                     AND c.fecha BETWEEN :fecha_hoy AND :fecha_manana 
                     AND c.estado = 'pendiente'
                   ORDER BY c.fecha ASC, c.hora ASC";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([
            ':barbero_id'   => $mi_id,
            ':fecha_hoy'    => $fecha_hoy,
            ':fecha_manana' => $fecha_manana
        ]);
    }
    $citasAgenda = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // 3. HORARIOS PUBLICADOS POR EL USUARIO LOGUEADO
    $sqlMisHorarios = "SELECT id, fecha, TIME_FORMAT(hora, '%H:%i') as hora_corta, estado 
                       FROM disponibilidad_barberos 
                       WHERE barbero_id = :barbero_id AND fecha >= :fecha_hoy
                       ORDER BY fecha ASC, hora ASC";
    $stmtMisHorarios = $pdo->prepare($sqlMisHorarios);
    $stmtMisHorarios->execute([
        ':barbero_id' => $mi_id,
        ':fecha_hoy'  => $fecha_hoy
    ]);
    $misHorarios = $stmtMisHorarios->fetchAll(PDO::FETCH_ASSOC);

    // 4. LISTA DE BARBEROS PARA GESTIÓN (ADMIN)
    $barberosGestion = [];
    if ($es_admin) {
        $stmtBG = $pdo->prepare("SELECT id, nombre, email, estado, rol FROM usuarios WHERE id != :mi_id AND rol = 'barbero'");
        $stmtBG->execute([':mi_id' => $mi_id]);
        $barberosGestion = $stmtBG->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error al cargar el panel: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberControl - Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111827] text-slate-100 font-sans min-h-screen flex flex-col md:flex-row">

    <aside class="w-full md:w-64 bg-[#1f2937] border-b md:border-b-0 md:border-r border-slate-700/50 p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-center space-x-3 mb-8">
                <div class="bg-amber-500 text-slate-950 font-black p-2 rounded-lg text-sm tracking-tighter">BC</div>
                <span class="text-lg font-bold text-amber-500 tracking-wide uppercase">BarberControl</span>
            </div>

            <nav class="space-y-2">
                <a href="admin_dashboard.php" class="flex items-center space-x-3 bg-amber-500 text-slate-950 font-bold px-4 py-3 rounded-xl transition shadow-lg shadow-amber-500/10">
                    <span>📅</span> <span>Mi Agenda</span>
                </a>
                <a href="backend/logout.php" class="flex items-center space-x-3 text-rose-400 hover:bg-rose-500/10 px-4 py-3 rounded-xl transition font-semibold">
                    <span>🚪</span> <span>Cerrar Sesión</span>
                </a>
            </nav>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-700/50 hidden md:block">
            <p class="text-xs text-slate-500 uppercase tracking-wider">Sesión: <?php echo ucfirst($mi_rol); ?></p>
            <p class="text-sm font-semibold text-slate-300"><?php echo htmlspecialchars($mi_nombre); ?></p>
        </div>
    </aside>

    <main class="flex-1 p-6 md:p-8 space-y-8 overflow-y-auto">
        
        <div class="border-b border-slate-700/50 pb-5">
            <h1 class="text-2xl font-extrabold text-slate-100">¡Hola, <?php echo htmlspecialchars($mi_nombre); ?>!</h1>
            <p class="text-sm text-slate-400">Panel de Administración Global y Personal de Agenda.</p>
        </div>

        <!-- VER CITAS DE LOS BARBEROS (Solo Admin) -->
        <?php if ($es_admin): ?>
        <section class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-3">
            <div>
                <h2 class="text-sm font-bold text-amber-500 flex items-center space-x-2">
                    <span>👥</span> <span>Ver las citas de los barberos</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Selecciona un barbero para consultar sus turnos confirmados entre hoy y mañana.</p>
            </div>

            <form action="admin_dashboard.php" method="GET" class="flex flex-col sm:flex-row gap-3 pt-1">
                <select name="barbero_ver" class="flex-1 bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500">
                    <option value="">-- Citas del Administrador (Mis Citas) --</option>
                    <?php foreach ($barberosLista as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($barbero_seleccionado == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-5 py-2.5 rounded-xl text-xs uppercase tracking-wide transition">
                        Filtrar Citas
                    </button>
                    <?php if ($barbero_seleccionado): ?>
                        <a href="admin_dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-xs flex items-center justify-center transition">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- SECCIÓN DE TURNOS (HOY Y MAÑANA) -->
        <section class="bg-[#1f2937] p-6 rounded-2xl border border-slate-700/50 shadow-xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-700/60 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-amber-500 flex items-center space-x-2">
                        <span>📅</span> <span>Turnos Confirmados (Hoy y Mañana)</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?php 
                        if ($es_admin && $barbero_seleccionado) {
                            $nombreBarberoFiltro = '';
                            foreach($barberosLista as $b) { 
                                if($b['id'] == $barbero_seleccionado) $nombreBarberoFiltro = $b['nombre']; 
                            }
                            echo "Mostrando citas del barbero: <b>" . htmlspecialchars($nombreBarberoFiltro) . "</b>";
                        } else {
                            echo "Mostrando únicamente las citas del Administrador.";
                        }
                        ?>
                    </p>
                </div>
                <span class="bg-amber-500/10 text-amber-400 px-3 py-1 rounded-full text-xs font-bold border border-amber-500/20">
                    <?php echo count($citasAgenda); ?> turnos
                </span>
            </div>

            <div class="space-y-2.5 max-h-[400px] overflow-y-auto pr-1">
                <?php if (empty($citasAgenda)): ?>
                    <p class="text-xs text-slate-500 italic py-8 text-center bg-[#111827]/40 rounded-xl border border-dashed border-slate-800">
                        No hay citas confirmadas para hoy ni para mañana.
                    </p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($citasAgenda as $cita): ?>
                            <?php 
                                $esHoy = ($cita['fecha'] == $fecha_hoy);
                                $etiquetaFecha = $esHoy ? '☀️ Hoy' : '🌙 Mañana';
                                $claseBorde = $esHoy ? 'border-amber-500/30 bg-[#111827]' : 'border-slate-800 bg-[#111827]';
                            ?>
                            <div class="border <?php echo $claseBorde; ?> p-3.5 rounded-xl flex items-center justify-between hover:border-slate-700 transition">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo $esHoy ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-slate-800 text-slate-300 border-slate-700'; ?>">
                                            <?php echo $etiquetaFecha; ?> (<?php echo date('d/m', strtotime($cita['fecha'])); ?>)
                                        </span>
                                        <span class="text-xs font-black text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                            <?php echo $cita['hora_corta']; ?>
                                        </span>
                                    </div>
                                    <div class="text-sm font-bold text-slate-200">
                                        <?php echo htmlspecialchars($cita['nombre_cliente']); ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        Barbero: <b><?php echo htmlspecialchars($cita['nombre_barbero']); ?></b>
                                    </div>
                                    <a href="https://wa.me/<?php echo $cita['telefono']; ?>" target="_blank" class="text-xs text-emerald-400 flex items-center space-x-1 hover:underline pt-0.5">
                                        <span>📱</span> <span>WhatsApp</span>
                                    </a>
                                </div>
                                <div>
                                    <a href="backend/cancelar_cita.php?id=<?php echo $cita['id']; ?>" onclick="return confirm('¿Seguro que deseas cancelar la cita de <?php echo htmlspecialchars($cita['nombre_cliente']); ?>?')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-slate-950 px-2.5 py-1.5 rounded-lg text-xs transition inline-block font-bold" title="Cancelar Cita">
                                        ❌ Cancelar
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- DISPONIBILIDAD Y GESTIÓN -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 border-t border-slate-700/30 pt-4">
            
            <div class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-4 lg:col-span-1">
                <h3 class="text-base font-bold text-amber-500 flex items-center space-x-2">
                    <span>⚡</span> <span>Abrir Nuevas Horas</span>
                </h3>
                <p class="text-xs text-slate-400">Habilita bloques de atención para fechas presentes o futuras.</p>
                
                <form action="backend/guardar_disponibilidad.php" method="POST" class="space-y-3 pt-1">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Día de Atención</label>
                        <input 
                            type="date" 
                            id="input-fecha"
                            name="fecha" 
                            min="<?php echo $fecha_hoy; ?>" 
                            value="<?php echo $fecha_hoy; ?>" 
                            required 
                            class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                        >
                    </div>

                    <div>
                        <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block mb-1">Hora de Inicio</label>
                        <input 
                            type="time" 
                            id="input-hora"
                            name="hora" 
                            required 
                            class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                        >
                    </div>

                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold py-2.5 rounded-xl text-xs uppercase tracking-wide transition">
                        Habilitar Horario
                    </button>
                </form>
            </div>

            <div class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-4 lg:col-span-2">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-amber-500">⏰ Mi Disponibilidad Habilitada</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Bloques publicados activos para recibir citas.</p>
                    </div>
                    <span class="bg-slate-800 border border-slate-700 text-slate-300 px-2.5 py-1 rounded-full text-xs font-semibold">
                        <?php echo count($misHorarios); ?> bloques
                    </span>
                </div>

                <div class="max-h-[260px] overflow-y-auto pr-1">
                    <?php if (empty($misHorarios)): ?>
                        <p class="text-xs text-slate-500 italic py-8 text-center bg-[#111827]/40 rounded-xl border border-dashed border-slate-800">
                            No has habilitado ningún bloque de disponibilidad aún.
                        </p>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <?php foreach ($misHorarios as $h): ?>
                                <div class="bg-[#111827] border <?php echo ($h['estado'] == 'ocupado') ? 'border-amber-500/30' : 'border-slate-800'; ?> p-2.5 rounded-xl flex items-center justify-between transition">
                                    <div class="space-y-0.5">
                                        <div class="text-[11px] font-bold text-amber-400">
                                            📅 <?php echo date('d/m', strtotime($h['fecha'])); ?>
                                        </div>
                                        <div class="text-sm font-black text-slate-200"><?php echo $h['hora_corta']; ?></div>
                                    </div>

                                    <div>
                                        <?php if ($h['estado'] == 'ocupado'): ?>
                                            <span class="text-[10px] bg-amber-500/10 text-amber-400 px-2 py-1 rounded-lg border border-amber-500/20 font-bold uppercase select-none">
                                                Reservado
                                            </span>
                                        <?php else: ?>
                                            <a href="backend/eliminar_horario.php?id=<?php echo $h['id']; ?>" 
                                               onclick="return confirm('¿Quieres retirar este horario disponible del sistema público?')" 
                                               class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white p-1.5 rounded-lg text-xs transition inline-block" 
                                               title="Retirar Horario">
                                                🗑️
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <?php if ($es_admin): ?>
            <div class="border-t border-slate-700/50 pt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-4 lg:col-span-1">
                    <h3 class="text-base font-bold text-amber-500">➕ Agregar Nuevo Barbero</h3>
                    <form action="backend/gestionar_barberos.php?accion=crear" method="POST" class="space-y-3">
                        <input type="text" name="nombre" required placeholder="Nombre Completo" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500">
                        <input type="email" name="correo" required placeholder="Correo de ingreso" class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500">
                        
                        <div class="relative w-full">
                            <input type="password" id="input-password-provisoria" name="password" required placeholder="Contraseña provisoria" 
                                class="w-full bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 pr-10 text-xs text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-amber-500 transition select-none text-xs">
                                👁️
                            </button>
                        </div>

                        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold py-2.5 rounded-xl text-xs uppercase tracking-wide transition">Contratar Barbero</button>
                    </form>
                </div>

                <div class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-4 lg:col-span-2">
                    <h3 class="text-base font-bold text-amber-500">👥 Plantilla de Barberos</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-700 text-slate-400 uppercase tracking-wider">
                                    <th class="pb-2 font-semibold">Nombre</th>
                                    <th class="pb-2 font-semibold">Correo</th>
                                    <th class="pb-2 font-semibold">Rol</th>
                                    <th class="pb-2 font-semibold text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/40 text-slate-300">
                                <?php foreach ($barberosGestion as $b): ?>
                                    <tr>
                                        <td class="py-3 font-medium text-slate-200"><?php echo htmlspecialchars($b['nombre']); ?></td>
                                        <td class="py-3 text-slate-400"><?php echo htmlspecialchars($b['email']); ?></td>
                                        <td class="py-3"><span class="bg-slate-700 text-slate-300 px-2 py-0.5 rounded text-[10px] uppercase font-bold"><?php echo htmlspecialchars($b['rol']); ?></span></td>
                                        <td class="py-3 text-right space-x-2">
                                            <a href="editar_barbero.php?id=<?php echo $b['id']; ?>" class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-slate-950 px-2.5 py-1.5 rounded-lg border border-amber-500/20 transition inline-block">Editar</a>
                                            <a href="backend/gestionar_barberos.php?accion=eliminar&id=<?php echo $b['id']; ?>" onclick="return confirm('¿Seguro que deseas despedir/eliminar a este barbero del sistema?')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white px-2.5 py-1.5 rounded-lg border border-rose-500/20 transition inline-block">Despedir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'creado') alert('✅ ¡Nuevo barbero incorporado al sistema con éxito!');
        if (urlParams.get('msg') === 'actualizado') alert('✅ Información del barbero actualizada correctamente.');
        if (urlParams.get('msg') === 'eliminado') alert('🗑️ El barbero ha sido eliminado del sistema.');
        if (urlParams.get('msg') === 'cita_cancelada') alert('❌ La cita ha sido cancelada y el horario liberado.');
        if (urlParams.get('msg') === 'horarios_guardados') alert('📆 ¡Tus horarios se han publicado correctamente!');
        if (urlParams.get('msg') === 'horario_eliminado') alert('🗑️ El bloque horario disponible ha sido removido con éxito.');
        if (urlParams.get('msg') === 'horario_duplicado') alert('⚠️ Ese bloque de horario ya lo tienes habilitado para ese día.');
        if (urlParams.get('msg') === 'hora_pasada') alert('⚠️ La hora seleccionada ya transcurrió hoy. Por favor elige una hora futura.');
        if (urlParams.get('msg') === 'fecha_invalida') alert('⚠️ No puedes seleccionar fechas pasadas.');

        function togglePassword() {
            const inputPass = document.getElementById("input-password-provisoria");
            if (inputPass) {
                inputPass.type = inputPass.type === "password" ? "text" : "password";
            }
        }

        const inputFecha = document.getElementById('input-fecha');
        const inputHora = document.getElementById('input-hora');

        function actualizarMinHora() {
            if (!inputFecha || !inputHora) return;

            const ahora = new Date();
            const ano = ahora.getFullYear();
            const mes = String(ahora.getMonth() + 1).padStart(2, '0');
            const dia = String(ahora.getDate()).padStart(2, '0');
            const fechaHoyStr = `${ano}-${mes}-${dia}`;

            if (inputFecha.value === fechaHoyStr) {
                const horas = String(ahora.getHours()).padStart(2, '0');
                const minutos = String(ahora.getMinutes()).padStart(2, '0');
                const horaActual = `${horas}:${minutos}`;

                inputHora.min = horaActual;

                if (inputHora.value && inputHora.value < horaActual) {
                    inputHora.value = '';
                }
            } else {
                inputHora.removeAttribute('min');
            }
        }

        if (inputFecha && inputHora) {
            inputFecha.addEventListener('change', actualizarMinHora);
            inputHora.addEventListener('change', actualizarMinHora);
            document.addEventListener('DOMContentLoaded', actualizarMinHora);
        }
    </script>
</body>
</html>