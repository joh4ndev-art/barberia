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

// CAPTURA DEL MES SELECCIONADO (Si no se envía, por defecto usa el mes y año actual YYYY-MM)
$mes_seleccionado = isset($_GET['mes']) && $_GET['mes'] !== '' ? $_GET['mes'] : date('Y-m');

// Calcular primer y último día del mes seleccionado
$primer_dia_mes = $mes_seleccionado . '-01';
$ultimo_dia_mes = date('Y-m-t', strtotime($primer_dia_mes));

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

    // 2. FILTRAR CITAS (Según el mes y/o barbero elegido)
    $barbero_seleccionado = isset($_GET['barbero_ver']) && $_GET['barbero_ver'] !== '' ? intval($_GET['barbero_ver']) : null;

    if ($es_admin && $barbero_seleccionado) {
        // Citas del barbero específico en el mes seleccionado
        $sqlCitas = "SELECT c.id, c.fecha, cl.nombre AS nombre_cliente, cl.telefono, TIME_FORMAT(c.hora, '%H:%i') as hora_corta, u.nombre AS nombre_barbero, c.notas 
                   FROM citas c 
                   JOIN usuarios u ON c.barbero_id = u.id 
                   JOIN clientes cl ON c.cliente_id = cl.id 
                   WHERE c.barbero_id = :barbero_id 
                     AND c.fecha BETWEEN :primer_dia AND :ultimo_dia 
                     AND c.estado = 'pendiente'
                   ORDER BY c.fecha ASC, c.hora ASC";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([
            ':barbero_id' => $barbero_seleccionado,
            ':primer_dia' => $primer_dia_mes,
            ':ultimo_dia' => $ultimo_dia_mes
        ]);
    } else {
        // Citas del Administrador / Barbero en el mes seleccionado
        $sqlCitas = "SELECT c.id, c.fecha, cl.nombre AS nombre_cliente, cl.telefono, TIME_FORMAT(c.hora, '%H:%i') as hora_corta, u.nombre AS nombre_barbero, c.notas 
                   FROM citas c 
                   JOIN usuarios u ON c.barbero_id = u.id 
                   JOIN clientes cl ON c.cliente_id = cl.id 
                   WHERE c.barbero_id = :barbero_id 
                     AND c.fecha BETWEEN :primer_dia AND :ultimo_dia 
                     AND c.estado = 'pendiente'
                   ORDER BY c.fecha ASC, c.hora ASC";
        $stmtC = $pdo->prepare($sqlCitas);
        $stmtC->execute([
            ':barbero_id' => $mi_id,
            ':primer_dia' => $primer_dia_mes,
            ':ultimo_dia' => $ultimo_dia_mes
        ]);
    }
    $citasAgenda = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // 3. HORARIOS PUBLICADOS POR EL USUARIO LOGUEADO
    // (Oculta automáticamente los bloques de citas que ya fueron terminadas o canceladas)
    $sqlMisHorarios = "SELECT d.id, d.fecha, TIME_FORMAT(d.hora, '%H:%i') as hora_corta, d.estado 
                       FROM disponibilidad_barberos d
                       LEFT JOIN citas c 
                              ON d.barbero_id = c.barbero_id 
                             AND d.fecha = c.fecha 
                             AND d.hora = c.hora 
                             AND c.estado IN ('terminado', 'finalizada', 'cancelada')
                       WHERE d.barbero_id = :barbero_id 
                         AND d.fecha >= :fecha_hoy
                         AND c.id IS NULL
                       ORDER BY d.fecha ASC, d.hora ASC";
    $stmtMisHorarios = $pdo->prepare($sqlMisHorarios);
    $stmtMisHorarios->execute([
        ':barbero_id' => $mi_id,
        ':fecha_hoy'  => $fecha_hoy
    ]);
    $misHorarios = $stmtMisHorarios->fetchAll(PDO::FETCH_ASSOC);

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
<body class="bg-[#111827] text-slate-100 font-sans min-h-screen relative overflow-x-hidden">

    <!-- BOTÓN TIPO HAMBURGUESA / FLOTANTE (SIEMPRE VISIBLE) -->
    <button 
        type="button" 
        onclick="toggleSidebar()" 
        class="fixed top-4 left-4 z-50 flex items-center gap-2 bg-[#1f2937] border border-slate-700/80 p-2 pr-3 rounded-xl hover:bg-slate-800 transition shadow-2xl cursor-pointer focus:outline-none"
        title="Abrir Menú">
        <div class="w-9 h-9 bg-amber-500 rounded-lg flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-amber-500/20">
            BC
        </div>
        <span class="font-extrabold text-amber-500 text-sm tracking-wide hidden sm:inline">BARBERCONTROL</span>
    </button>

    <!-- CAPA OSCURA DE FONDO (BACKDROP PARA CERRAR AL HACER CLIC AFUERA) -->
    <div 
        id="sidebarBackdrop" 
        onclick="toggleSidebar()" 
        class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 hidden transition-opacity duration-300">
    </div>

    <!-- SIDEBAR COLAPSABLE (OCULTO POR DEFECTO CON '-translate-x-full') -->
    <aside 
        id="sidebarMenu" 
        class="fixed top-0 left-0 h-full w-64 bg-[#1f2937] border-r border-slate-700/50 p-6 flex flex-col justify-between z-50 -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl">
        
        <div class="space-y-6">
            <!-- CABECERA DEL SIDEBAR -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center text-slate-950 font-black text-base shadow-lg shadow-amber-500/20">
                        BC
                    </div>
                    <span class="font-extrabold text-amber-500 tracking-wide text-base">BARBERCONTROL</span>
                </div>
                <!-- Botón 'X' para cerrar -->
                <button type="button" onclick="toggleSidebar()" class="text-slate-400 hover:text-slate-100 text-xl font-bold p-1">
                    ✕
                </button>
            </div>

            <!-- NAVEGACIÓN Y OPCIONES -->
            <nav class="flex flex-col gap-2">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-amber-500 text-slate-950 font-bold rounded-xl text-sm shadow-md transition">
                    <span>📅</span>
                    <span>Mi Agenda</span>
                </a>

                <!-- SOLO VISIBLE SI ES ADMIN -->
                <?php if (isset($es_admin) && $es_admin): ?>
                    <a href="gestion_barberos.php" class="flex items-center gap-3 px-4 py-3 text-slate-300 hover:bg-slate-800/80 font-bold rounded-xl text-sm transition">
                        <span>👥</span>
                        <span>Gestión Barberos</span>
                    </a>
                <?php endif; ?>

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

        <!-- PIE DEL SIDEBAR (DATOS DE SESIÓN) -->
        <div class="pt-6 border-t border-slate-700/50">
            <p class="text-xs text-slate-500 uppercase tracking-wider">Sesión: <?php echo ucfirst($mi_rol); ?></p>
            <p class="text-sm font-semibold text-slate-300"><?php echo htmlspecialchars($mi_nombre); ?></p>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL (AHORA TOMA EL 100% DE LA PANTALLA) -->
    <main class="w-full min-h-screen p-6 md:p-8 pt-20 space-y-8 overflow-y-auto">
        
        <!-- ENCABEZADO PRINCIPAL (ALINEADO CON EL BOTÓN DEL MENÚ) -->
        <div class="border-b border-slate-700/50 pb-5 pt-2 flex flex-col justify-center pl-48 sm:pl-56 transition-all">
            <h1 class="text-2xl font-extrabold text-slate-100 flex items-center gap-2">
                ¡Hola, <?php echo htmlspecialchars($mi_nombre); ?>!
            </h1>
            <p class="text-sm text-slate-400">Panel de Administración Global y Personal de Agenda.</p>
        </div>

        <!-- FILTRO DE AGENDA POR MES Y BARBERO -->
        <section class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl space-y-3">
            <div>
                <h2 class="text-sm font-bold text-amber-500 flex items-center space-x-2">
                    <span>🔍</span> <span>Filtrar Citas de la Agenda</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Selecciona un mes<?php echo $es_admin ? ' o un barbero específico' : ''; ?> para consultar los turnos.</p>
            </div>

            <form action="admin_dashboard.php" method="GET" class="flex flex-col sm:flex-row gap-3 pt-1">
                
                <!-- Selector de Mes -->
                <input 
                    type="month" 
                    name="mes" 
                    value="<?php echo htmlspecialchars($mes_seleccionado); ?>" 
                    class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                >

                <!-- Selector de Barbero (Solo Visible para Admin) -->
                <?php if ($es_admin): ?>
                <select name="barbero_ver" class="flex-1 bg-[#111827] border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500">
                    <option value="">-- Citas del Administrador (Mis Citas) --</option>
                    <?php foreach ($barberosLista as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($barbero_seleccionado == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <div class="flex gap-2">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-5 py-2.5 rounded-xl text-xs uppercase tracking-wide transition">
                        Filtrar
                    </button>
                    <?php if ($barbero_seleccionado || isset($_GET['mes'])): ?>
                        <a href="admin_dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-xs flex items-center justify-center transition">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- SECCIÓN DE TURNOS DEL MES -->
        <section class="bg-[#1f2937] p-6 rounded-2xl border border-slate-700/50 shadow-xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-700/60 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-amber-500 flex items-center space-x-2">
                        <span>📅</span> 
                        <span>Turnos Confirmados (<?php echo date('m/Y', strtotime($primer_dia_mes)); ?>)</span>
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
                        No hay citas confirmadas pendientes para este período.
                    </p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($citasAgenda as $cita): ?>
                            <?php 
                                $esHoy = ($cita['fecha'] == $fecha_hoy);
                                $esManana = ($cita['fecha'] == date('Y-m-d', strtotime('+1 day')));
                                
                                if ($esHoy) {
                                    $etiquetaFecha = '☀️ Hoy';
                                    $claseBorde = 'border-amber-500/30 bg-[#111827]';
                                } elseif ($esManana) {
                                    $etiquetaFecha = '🌙 Mañana';
                                    $claseBorde = 'border-slate-800 bg-[#111827]';
                                } else {
                                    $etiquetaFecha = '📅 ' . date('d/m/Y', strtotime($cita['fecha']));
                                    $claseBorde = 'border-slate-800 bg-[#111827]';
                                }

                                // Preparación limpia de variables para el modal de Finalizar Cita
                                $clienteNombreEscapado = htmlspecialchars($cita['nombre_cliente'], ENT_QUOTES, 'UTF-8');
                                $corteDetalleEscapado = htmlspecialchars($cita['notas'] ?? '', ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="border <?php echo $claseBorde; ?> p-3.5 rounded-xl flex items-start justify-between hover:border-slate-700 transition">
                                <div class="space-y-1.5 flex-1 pr-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo $esHoy ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-slate-800 text-slate-300 border-slate-700'; ?>">
                                            <?php echo $etiquetaFecha; ?>
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

                                    <!-- VISTA DE DETALLES/DESCRIPCIÓN DEL CORTE -->
                                    <div class="bg-[#1f2937] border border-slate-700/60 p-2 rounded-lg text-xs mt-1">
                                        <span class="font-bold text-amber-500 text-[11px] block mb-0.5">✂️ Corte / Detalle:</span>
                                        <p class="text-slate-300 italic text-[11px] leading-tight">
                                            <?php echo !empty($cita['notas']) ? htmlspecialchars($cita['notas']) : 'Sin especificación'; ?>
                                        </p>
                                    </div>

                                    <a href="https://wa.me/<?php echo $cita['telefono']; ?>" target="_blank" class="text-xs text-emerald-400 flex items-center space-x-1 hover:underline pt-1">
                                        <span>📱</span> <span>WhatsApp: <?php echo htmlspecialchars($cita['telefono']); ?></span>
                                    </a>
                                </div>

                                <!-- ACCIONES DE CITA -->
                                <div class="flex flex-col gap-2">
                                    <button 
                                        type="button" 
                                        onclick="abrirModalGestion('recordar', '<?php echo $cita['id']; ?>', '<?php echo htmlspecialchars(addslashes($cita['nombre_cliente'])); ?>', '<?php echo $cita['telefono']; ?>', '<?php echo date('d/m/Y', strtotime($cita['fecha'])); ?>', '<?php echo $cita['hora_corta']; ?>')" 
                                        class="bg-blue-500/10 hover:bg-blue-500 text-blue-400 hover:text-slate-950 px-2.5 py-1.5 rounded-lg text-xs transition font-bold text-center" 
                                        title="Enviar Recordatorio">
                                        🔔 Recordar
                                    </button>
                                    <button 
                                        type="button" 
                                        onclick="abrirModalGestion('cancelar', '<?php echo $cita['id']; ?>', '<?php echo htmlspecialchars(addslashes($cita['nombre_cliente'])); ?>', '<?php echo $cita['telefono']; ?>', '<?php echo date('d/m/Y', strtotime($cita['fecha'])); ?>', '<?php echo $cita['hora_corta']; ?>')" 
                                        class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-slate-950 px-2.5 py-1.5 rounded-lg text-xs transition font-bold text-center" 
                                        title="Cancelar Cita">
                                        ❌ Cancelar
                                    </button>
                                    <button 
                                        type="button" 
                                        onclick="abrirModalGestion('reubicar', '<?php echo $cita['id']; ?>', '<?php echo htmlspecialchars(addslashes($cita['nombre_cliente'])); ?>', '<?php echo $cita['telefono']; ?>', '<?php echo date('d/m/Y', strtotime($cita['fecha'])); ?>', '<?php echo $cita['hora_corta']; ?>')" 
                                        class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-slate-950 px-2.5 py-1.5 rounded-lg text-xs transition font-bold text-center" 
                                        title="Reubicar Cita">
                                        🔄 Reubicar
                                    </button>
                                    <!-- BOTÓN FINALIZAR (CORREGIDO) -->
                                    <button 
                                        type="button" 
                                        onclick="abrirModalFinalizar(<?php echo $cita['id']; ?>, '<?php echo $clienteNombreEscapado; ?>', '<?php echo $corteDetalleEscapado; ?>', 0)" 
                                        class="px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                                        <span>💰</span>
                                        <span>Finalizar</span>
                                    </button>
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

    </main>

    <!-- MODAL DE CONFIRMACIÓN / MENSAJE WHATSAPP -->
    <div id="modalGestion" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-[#1f2937] border border-slate-700 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-700 pb-3">
                <h3 id="modalTitulo" class="text-lg font-bold text-amber-500">Gestión de Cita</h3>
                <button type="button" onclick="cerrarModal()" class="text-slate-400 hover:text-white font-bold text-lg leading-none">&times;</button>
            </div>
            
            <p id="modalDescripcion" class="text-sm text-slate-300"></p>

            <div class="bg-[#111827] p-3.5 rounded-xl border border-slate-800 space-y-1">
                <p class="text-xs text-slate-400 font-semibold">Mensaje a enviar por WhatsApp:</p>
                <p id="modalMensajePreview" class="text-xs text-amber-400 italic break-words"></p>
            </div>

            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="cerrarModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-4 py-2.5 rounded-xl font-bold transition">
                    Cancelar
                </button>
                <button id="btnEnviarWhatsApp" type="button" class="bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-xs px-4 py-2.5 rounded-xl font-bold transition flex items-center space-x-2">
                    <span>📱</span> <span>Enviar mensaje por WhatsApp</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales para el modal de gestión
        let citaActualId = null;
        let accionTipo = null;
        let urlWhatsApp = '';

        // FUNCIÓN PARA CERRAR EL MODAL DE GESTIÓN
        function cerrarModal() {
            const modal = document.getElementById('modalGestion');
            if (modal) {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }
            // Limpiamos las variables temporales
            citaActualId = null;
            accionTipo = null;
            urlWhatsApp = '';
        }

        // ABRIR MODAL DE GESTIÓN (CANCELAR, REUBICAR, RECORDAR)
        function abrirModalGestion(accion, citaId, clienteNombre, telefono, fecha, hora) {
            citaActualId = citaId;
            accionTipo = accion;

            const modal = document.getElementById('modalGestion');
            const titulo = document.getElementById('modalTitulo');
            const descripcion = document.getElementById('modalDescripcion');
            const preview = document.getElementById('modalMensajePreview');

            if (!modal || !titulo || !descripcion || !preview) return;

            // Limpiar número para formato internacional de WhatsApp
            let telLimpio = String(telefono).replace(/\D/g, '');

            let mensaje = '';
            if (accion === 'cancelar') {
                titulo.innerText = '❌ Cancelar Cita';
                descripcion.innerText = `¿Seguro que deseas cancelar la cita de ${clienteNombre} del ${fecha} a las ${hora}?`;
                mensaje = `Hola ${clienteNombre}, te informamos que lamentablemente hemos tenido que cancelar tu cita del día ${fecha} a las ${hora}. Disculpa los inconvenientes.`;
            } else if (accion === 'reubicar') {
                titulo.innerText = '🔄 Reubicar Cita';
                descripcion.innerText = `Vas a solicitar reubicar la cita de ${clienteNombre} programada para el ${fecha} a las ${hora}.`;
                mensaje = `Hola ${clienteNombre}, necesitamos reubicar tu cita del día ${fecha} a las ${hora}. Por favor indícanos qué otro horario te conviene. ¡Gracias!`;
            } else if (accion === 'recordar') {
                titulo.innerText = '🔔 Recordatorio de Cita';
                descripcion.innerText = `Enviarás un mensaje de recordatorio a ${clienteNombre} para su cita del ${fecha} a las ${hora}.`;
                mensaje = `Hola ${clienteNombre} 👋, te recordamos tu cita agendada para el día ${fecha} a las ${hora} 💈. ¡Te esperamos a tiempo!`;
            }

            preview.innerText = `"${mensaje}"`;
            urlWhatsApp = `https://wa.me/${telLimpio}?text=${encodeURIComponent(mensaje)}`;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // CONTROL DE MENÚ DESPLEGABLE DE USUARIO
        function toggleMenuDropdown() {
            const menu = document.getElementById('menuDropdown');
            const flecha = document.getElementById('flechaMenu');
            
            if (menu) {
                const estaOculto = menu.classList.contains('hidden');
                if (estaOculto) {
                    menu.classList.remove('hidden');
                    menu.classList.add('flex');
                    if (flecha) flecha.classList.add('rotate-180');
                } else {
                    menu.classList.remove('flex');
                    menu.classList.add('hidden');
                    if (flecha) flecha.classList.remove('rotate-180');
                }
            }
        }

        // CONTROL DE SIDEBAR EN MÓVILES
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarMenu');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (sidebar && backdrop) {
                const estaOculto = sidebar.classList.contains('-translate-x-full');

                if (estaOculto) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.remove('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('hidden');
                }
            }
        }

        // VALIDACIÓN DE HORA MÍNIMA PARA HOY
        function actualizarMinHora() {
            const inputFecha = document.getElementById('input-fecha');
            const inputHora = document.getElementById('input-hora');

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

        // EVENTOS AL CARGAR EL DOM
        document.addEventListener('DOMContentLoaded', function() {
            
            // 1. Asignar evento de envío por WhatsApp
            const btnEnviarWhatsApp = document.getElementById('btnEnviarWhatsApp');
            if (btnEnviarWhatsApp) {
                btnEnviarWhatsApp.addEventListener('click', function() {
                    if (!citaActualId || !urlWhatsApp) return;

                    // Abrir ventana de WhatsApp
                    window.open(urlWhatsApp, '_blank');

                    // Si es cancelar o reubicar, procesar en base de datos
                    if (accionTipo === 'cancelar' || accionTipo === 'reubicar') {
                        fetch('backend/procesar_cancelacion.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `cita_id=${encodeURIComponent(citaActualId)}&accion=${encodeURIComponent(accionTipo)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload(); 
                            } else {
                                alert('Error al actualizar el sistema: ' + (data.error || 'Desconocido'));
                            }
                        })
                        .catch(error => {
                            console.error('Error al procesar la cancelación:', error);
                        });
                    }

                    cerrarModal();
                });
            }

            // 2. Control de eventos para entradas de fecha y hora
            const inputFecha = document.getElementById('input-fecha');
            const inputHora = document.getElementById('input-hora');

            if (inputFecha && inputHora) {
                inputFecha.addEventListener('change', actualizarMinHora);
                inputHora.addEventListener('change', actualizarMinHora);
                actualizarMinHora();
            }

            // 3. Lectura de alertas desde URL y limpieza de parámetro 'msg'
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');

            if (msg) {
                const mensajesAlert = {
                    'creado': '✅ ¡Nuevo barbero incorporado al sistema con éxito!',
                    'actualizado': '✅ Información del barbero actualizada correctamente.',
                    'eliminado': '🗑️ El barbero ha sido eliminado del sistema.',
                    'cita_cancelada': '❌ La cita ha sido cancelada y el horario liberado.',
                    'horarios_guardados': '📆 ¡Tus horarios se han publicado correctamente!',
                    'horario_eliminado': '🗑️ El bloque horario disponible ha sido removido con éxito.',
                    'horario_duplicado': '⚠️ Ese bloque de horario ya lo tienes habilitado para ese día.',
                    'hora_pasada': '⚠️ La hora seleccionada ya transcurrió hoy. Por favor elige una hora futura.',
                    'fecha_invalida': '⚠️ No puedes seleccionar fechas pasadas.'
                };

                if (mensajesAlert[msg]) {
                    alert(mensajesAlert[msg]);
                }

                // Limpiar el parámetro 'msg' de la URL sin recargar la página
                urlParams.delete('msg');
                const nuevaUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, nuevaUrl);
            }

            // 4. Cierre del menú desplegable al hacer clic fuera
            document.addEventListener('click', function(event) {
                const menu = document.getElementById('menuDropdown');
                const boton = event.target.closest('button[onclick="toggleMenuDropdown()"]');
                
                if (menu && !menu.contains(event.target) && !boton) {
                    menu.classList.remove('flex');
                    menu.classList.add('hidden');
                    const flecha = document.getElementById('flechaMenu');
                    if (flecha) flecha.classList.remove('rotate-180');
                }
            });
        });
    </script>

    <!-- MODAL PARA FINALIZAR Y REGISTRAR PAGO -->
    <div id="modalFinalizarCita" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-[#1f2937] border border-slate-700/80 rounded-2xl p-6 w-full max-w-md shadow-2xl relative space-y-5">
            
            <!-- ENCABEZADO DE LA VENTANA -->
            <div class="flex justify-between items-center border-b border-slate-700/50 pb-3">
                <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    <span>💰</span> Finalizar y Registrar Pago
                </h3>
                <button type="button" onclick="cerrarModalFinalizar()" class="text-slate-400 hover:text-slate-100 text-lg font-bold leading-none" title="Cerrar modal">✕</button>
            </div>

            <!-- FORMULARIO -->
            <form action="backend/finalizar_cita.php" method="POST" class="space-y-4">
                <!-- ID Oculto de la Cita -->
                <input type="hidden" name="cita_id" id="modal_cita_id" value="">

                <!-- NOMBRE DEL CLIENTE -->
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Cliente:</p>
                    <p class="text-base font-extrabold text-slate-100 bg-[#111827] p-3 rounded-xl border border-slate-700/60 truncate" id="modal_cliente_nombre">-</p>
                </div>

                <!-- SERVICIO (Obligatorio) -->
                <div>
                    <label for="modal_servicio" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">
                        Servicio Realizado: <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="servicio_nombre" 
                        id="modal_servicio" 
                        required 
                        placeholder="ej: corte de cabello, perfilar barba, corte de cabello y barba..."
                        class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-slate-100 text-sm font-medium focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition placeholder:text-slate-500 placeholder:text-xs"
                    >
                </div>

                <!-- MONTO / GANANCIA (Obligatorio) -->
                <div>
                    <label for="modal_monto" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">
                        Monto / Ganancia Obtenida ($): <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="number" 
                        name="monto" 
                        id="modal_monto" 
                        required 
                        min="0" 
                        step="500" 
                        placeholder="Ej. 35000"
                        class="w-full bg-[#111827] border border-slate-700 rounded-xl px-4 py-3 text-slate-100 text-lg font-bold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition placeholder:text-slate-500"
                    >
                </div>

                <!-- BOTONES -->
                <div class="flex gap-3 pt-2">
                    <button 
                        type="button" 
                        onclick="cerrarModalFinalizar()" 
                        class="w-1/2 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs uppercase tracking-wider transition cursor-pointer">
                        Cancelar
                    </button>
                    <button 
                        type="submit" 
                        class="w-1/2 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/20 transition cursor-pointer">
                        Confirmar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT DE MANEJO DEL MODAL DE FINALIZACIÓN -->
    <script>
    function abrirModalFinalizar(id, cliente, servicio, precioBase) {
        const inputId = document.getElementById('modal_cita_id');
        const labelCliente = document.getElementById('modal_cliente_nombre');
        const inputServicio = document.getElementById('modal_servicio');
        const inputMonto = document.getElementById('modal_monto');
        const modal = document.getElementById('modalFinalizarCita');

        if (inputId) inputId.value = id;
        if (labelCliente) labelCliente.textContent = (cliente && cliente.trim() !== '') ? cliente : 'Cliente no especificado';
        if (inputServicio) inputServicio.value = servicio ? servicio : '';
        if (inputMonto) inputMonto.value = (precioBase && precioBase > 0) ? precioBase : '';
        
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function cerrarModalFinalizar() {
        const modal = document.getElementById('modalFinalizarCita');
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    }

    // Cierre al presionar fuera del modal
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modalFinalizarCita');
        if (modal) {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    cerrarModalFinalizar();
                }
            });
        }
    });
    </script>
</body>
</html>