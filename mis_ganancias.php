<?php
session_start();

// Validar que exista la sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

// Datos de sesión
$mi_id = $_SESSION['usuario_id'];
$mi_nombre = $_SESSION['usuario_nombre'] ?? 'Barbero';
$mi_rol = strtolower(trim($_SESSION['usuario_rol'] ?? 'barbero')); // Normalizar texto del rol
$es_admin = ($mi_rol === 'admin');

// Incluir la conexión PDO definida en backend/conexion.php ($pdo)
require_once 'backend/conexion.php';

// Capturar filtros del usuario
$tipo_filtro = $_GET['tipo_filtro'] ?? 'mes'; // 'dia', 'semana', 'mes'
$fecha_dia = $_GET['fecha_dia'] ?? date('Y-m-d');
$fecha_semana = $_GET['fecha_semana'] ?? date('Y-\WW'); // Formato AAAA-Www
$fecha_mes = $_GET['fecha_mes'] ?? date('Y-m');

// Filtro de barbero (para el admin)
// 0 = Todos los barberos (solo aplica para admin)
$barbero_filtro = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : ($es_admin ? 0 : $mi_id);
if (!$es_admin) {
    $barbero_filtro = $mi_id; // Forzar seguridad si es barbero
}

// Variables por defecto
$ganancia_hoy = 0;
$ganancia_periodo = 0;
$citas_periodo = 0;
$historial = [];
$barberos_lista = [];
$titulo_periodo = "Mes Seleccionado";

/* 
 * CONSULTAS PDO CON CONTROL DE EXCEPCIONES
*/
if (isset($pdo)) {
    try {
        // 0. Obtener lista de barberos para el filtro del Admin
        if ($es_admin) {
            $stmt_barberos = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('barbero', 'admin') ORDER BY nombre ASC");
            $barberos_lista = $stmt_barberos->fetchAll();
        }

        // 1. Ganancias acumuladas del día de hoy (fijo como métrica rápida)
        $sql_hoy = "
            SELECT SUM(precio) AS total 
            FROM citas 
            WHERE estado = 'finalizada' 
              AND DATE(fecha) = CURDATE()
        ";
        $params_hoy = [];
        if ($barbero_filtro > 0) {
            $sql_hoy .= " AND barbero_id = :barbero_id";
            $params_hoy[':barbero_id'] = $barbero_filtro;
        }
        $stmt_hoy = $pdo->prepare($sql_hoy);
        $stmt_hoy->execute($params_hoy);
        $row_hoy = $stmt_hoy->fetch();
        $ganancia_hoy = $row_hoy['total'] ?? 0;

        // 2. Construcción del filtro SQL dinámico por fecha
        $where_filtro = "";
        $params = [];

        if ($barbero_filtro > 0) {
            $where_filtro .= " AND c.barbero_id = :barbero_id";
            $params[':barbero_id'] = $barbero_filtro;
        }

        if ($tipo_filtro === 'dia') {
            $where_filtro .= " AND DATE(c.fecha) = :fecha_dia";
            $params[':fecha_dia'] = $fecha_dia;
            $titulo_periodo = "Día (" . date('d/m/Y', strtotime($fecha_dia)) . ")";

        } elseif ($tipo_filtro === 'semana') {
            list($anio, $semana) = explode('-W', $fecha_semana);
            $dto = new DateTime();
            $dto->setISODate((int)$anio, (int)$semana);
            $inicio_semana = $dto->format('Y-m-d');
            $dto->modify('+6 days');
            $fin_semana = $dto->format('Y-m-d');

            $where_filtro .= " AND DATE(c.fecha) BETWEEN :inicio_semana AND :fin_semana";
            $params[':inicio_semana'] = $inicio_semana;
            $params[':fin_semana'] = $fin_semana;
            $titulo_periodo = "Semana del " . date('d/m', strtotime($inicio_semana)) . " al " . date('d/m/Y', strtotime($fin_semana));

        } else { // 'mes'
            $where_filtro .= " AND DATE_FORMAT(c.fecha, '%Y-%m') = :fecha_mes";
            $params[':fecha_mes'] = $fecha_mes;
            $titulo_periodo = "Mes (" . htmlspecialchars($fecha_mes) . ")";
        }

        // 3. Consulta Totales del Período
        $sql_periodo = "
            SELECT SUM(precio) AS total, COUNT(id) AS total_citas 
            FROM citas c
            WHERE c.estado = 'finalizada' 
              $where_filtro
        ";
        $stmt_periodo = $pdo->prepare($sql_periodo);
        $stmt_periodo->execute($params);
        $datos_periodo = $stmt_periodo->fetch();
        $ganancia_periodo = $datos_periodo['total'] ?? 0;
        $citas_periodo = $datos_periodo['total_citas'] ?? 0;

        // 4. Historial de servicios finalizados para la tabla
        $sql_historial = "
            SELECT 
                c.fecha, 
                c.hora, 
                c.servicio, 
                c.precio, 
                COALESCE(u.nombre, 'Cliente General') AS cliente_nombre,
                COALESCE(b.nombre, 'No asignado') AS barbero_nombre
            FROM citas c
            LEFT JOIN usuarios u ON c.cliente_id = u.id
            LEFT JOIN usuarios b ON c.barbero_id = b.id
            WHERE c.estado = 'finalizada'
              $where_filtro
            ORDER BY c.fecha DESC, c.hora DESC
        ";
        $stmt_historial = $pdo->prepare($sql_historial);
        $stmt_historial->execute($params);
        $historial = $stmt_historial->fetchAll();

    } catch (\PDOException $e) {
        error_log("Error PDO en mis_ganancias.php: " . $e->getMessage());
    }
}

// URL dinámica para exportar
$url_exportar = "backend/exportar_ganancias.php?" . http_build_query([
    'tipo_filtro'  => $tipo_filtro,
    'fecha_dia'    => $fecha_dia,
    'fecha_semana' => $fecha_semana,
    'fecha_mes'    => $fecha_mes,
    'barbero_id'   => $barbero_filtro
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberControl - Mis Ganancias</title>
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

                <?php if ($es_admin): ?>
                    <a href="gestion_barberos.php" class="flex items-center gap-3 px-4 py-3 text-slate-300 hover:bg-slate-800/80 font-bold rounded-xl text-sm transition">
                        <span>👥</span>
                        <span>Gestión Barberos</span>
                    </a>
                <?php endif; ?>

                <a href="mis_ganancias.php" class="flex items-center gap-3 px-4 py-3 bg-amber-500 text-slate-950 font-bold rounded-xl text-sm shadow-md transition">
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
            <p class="text-xs text-slate-500 uppercase tracking-wider">Sesión: <?php echo htmlspecialchars(ucfirst($mi_rol)); ?></p>
            <p class="text-sm font-semibold text-slate-300"><?php echo htmlspecialchars($mi_nombre); ?></p>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="w-full min-h-screen p-6 md:p-8 space-y-8 overflow-y-auto">
        
        <!-- HEADER ENCABEZADO -->
        <div class="border-b border-slate-700/50 pb-5 pt-2 flex flex-col justify-center pl-48 sm:pl-56 transition-all">
            <h1 class="text-2xl font-extrabold text-slate-100 flex items-center gap-2">
                💰 Resumen de Ganancias
            </h1>
            <p class="text-sm text-slate-400">Consulta los ingresos filtrados por día, semana o mes en tiempo real.</p>
        </div>

        <!-- FILTRO DE PERÍODO Y EXPORTACIÓN -->
        <section class="bg-[#1f2937] p-5 rounded-2xl border border-slate-700/50 shadow-xl max-w-4xl">
            <form action="mis_ganancias.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                
                <!-- SELECTOR DE BARBERO (SOLO ADMIN) -->
                <?php if ($es_admin): ?>
                <div class="flex flex-col gap-1 w-full">
                    <label class="text-xs text-slate-400 font-bold uppercase tracking-wider">Barbero:</label>
                    <select name="barbero_id" class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-amber-500">
                        <option value="0">-- Todos los Barberos --</option>
                        <?php foreach ($barberos_lista as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $barbero_filtro == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- TIPO DE FILTRO -->
                <div class="flex flex-col gap-1 w-full">
                    <label class="text-xs text-slate-400 font-bold uppercase tracking-wider">Ver Por:</label>
                    <select name="tipo_filtro" id="tipo_filtro" onchange="cambiarTipoFiltro()" class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emerald-500">
                        <option value="dia" <?php echo $tipo_filtro === 'dia' ? 'selected' : ''; ?>>Por Día</option>
                        <option value="semana" <?php echo $tipo_filtro === 'semana' ? 'selected' : ''; ?>>Por Semana</option>
                        <option value="mes" <?php echo $tipo_filtro === 'mes' ? 'selected' : ''; ?>>Por Mes</option>
                    </select>
                </div>

                <!-- SELECCIONAR FECHA -->
                <div class="flex flex-col gap-1 w-full">
                    <label class="text-xs text-slate-400 font-bold uppercase tracking-wider">Fecha:</label>
                    
                    <input 
                        type="date" 
                        name="fecha_dia" 
                        id="input_dia" 
                        value="<?php echo htmlspecialchars($fecha_dia); ?>" 
                        class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emerald-500 <?php echo $tipo_filtro !== 'dia' ? 'hidden' : ''; ?>"
                    >

                    <input 
                        type="week" 
                        name="fecha_semana" 
                        id="input_semana" 
                        value="<?php echo htmlspecialchars($fecha_semana); ?>" 
                        class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emerald-500 <?php echo $tipo_filtro !== 'semana' ? 'hidden' : ''; ?>"
                    >

                    <input 
                        type="month" 
                        name="fecha_mes" 
                        id="input_mes" 
                        value="<?php echo htmlspecialchars($fecha_mes); ?>" 
                        class="bg-[#111827] border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-emerald-500 <?php echo $tipo_filtro !== 'mes' ? 'hidden' : ''; ?>"
                    >
                </div>

                <!-- BOTONES CONSULTAR Y EXPORTAR -->
                <div class="flex gap-2 w-full col-span-1 sm:col-span-2 md:col-span-1">
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold px-4 py-2 rounded-xl text-xs uppercase tracking-wide transition flex-1 cursor-pointer">
                        Filtrar
                    </button>
                    
                    <a href="<?php echo $url_exportar; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs uppercase tracking-wide transition flex items-center justify-center gap-1.5 text-center shadow-lg" title="Exportar reporte a Excel">
                        📊 <span>Excel</span>
                    </a>
                </div>
            </form>
        </section>

        <!-- TARJETAS DE RESUMEN DE MÉTRICAS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="bg-[#1f2937] border border-slate-700/50 p-6 rounded-2xl shadow-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ganancias de Hoy</p>
                    <p class="text-3xl font-black text-emerald-400 mt-2">$<?php echo number_format($ganancia_hoy, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-2xl">
                    💵
                </div>
            </div>

            <div class="bg-[#1f2937] border border-slate-700/50 p-6 rounded-2xl shadow-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total <?php echo $titulo_periodo; ?></p>
                    <p class="text-3xl font-black text-amber-500 mt-2">$<?php echo number_format($ganancia_periodo, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-center justify-center text-2xl">
                    📊
                </div>
            </div>

            <div class="bg-[#1f2937] border border-slate-700/50 p-6 rounded-2xl shadow-xl flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Servicios Realizados</p>
                    <p class="text-3xl font-black text-sky-400 mt-2"><?php echo $citas_periodo; ?> <span class="text-xs font-normal text-slate-400">citas</span></p>
                </div>
                <div class="w-12 h-12 bg-sky-500/10 border border-sky-500/20 rounded-xl flex items-center justify-center text-2xl">
                    💈
                </div>
            </div>

        </div>

        <!-- TABLA CON HISTORIAL DE SERVICIOS -->
        <section class="bg-[#1f2937] rounded-2xl border border-slate-700/50 shadow-xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    📋 Historial de Servicios
                </h2>
                <span class="text-xs text-amber-400 font-semibold bg-amber-500/10 border border-amber-500/20 px-3 py-1 rounded-lg">
                    Filtrado por: <?php echo $titulo_periodo; ?>
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-[#111827] text-xs uppercase text-slate-400 border-b border-slate-700/50">
                        <tr>
                            <th class="p-4">Fecha / Hora</th>
                            <th class="p-4">Cliente</th>
                            <?php if ($es_admin): ?>
                                <th class="p-4">Barbero</th>
                            <?php endif; ?>
                            <th class="p-4">Servicio</th>
                            <th class="p-4 text-right">Valor Realizado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php if (!empty($historial)): ?>
                            <?php foreach ($historial as $fila): ?>
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="p-4 font-medium text-slate-200">
                                        <?php echo date('d/m/Y', strtotime($fila['fecha'])); ?> - <?php echo date('h:i A', strtotime($fila['hora'])); ?>
                                    </td>
                                    <td class="p-4"><?php echo htmlspecialchars($fila['cliente_nombre']); ?></td>
                                    <?php if ($es_admin): ?>
                                        <td class="p-4 text-amber-400 font-semibold"><?php echo htmlspecialchars($fila['barbero_nombre']); ?></td>
                                    <?php endif; ?>
                                    <td class="p-4">
                                        <span class="bg-amber-500/10 text-amber-400 px-2.5 py-1 rounded-lg border border-amber-500/20 text-xs font-semibold">
                                            <?php echo htmlspecialchars($fila['servicio'] ?? 'Corte General'); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right font-bold text-emerald-400">
                                        $<?php echo number_format($fila['precio'] ?? 0, 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $es_admin ? 5 : 4; ?>" class="p-6 text-center text-slate-500">
                                    No hay registros de citas finalizadas para este período.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

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

        function cambiarTipoFiltro() {
            const tipo = document.getElementById('tipo_filtro').value;
            const inputDia = document.getElementById('input_dia');
            const inputSemana = document.getElementById('input_semana');
            const inputMes = document.getElementById('input_mes');

            inputDia.classList.add('hidden');
            inputSemana.classList.add('hidden');
            inputMes.classList.add('hidden');

            if (tipo === 'dia') {
                inputDia.classList.remove('hidden');
            } else if (tipo === 'semana') {
                inputSemana.classList.remove('hidden');
            } else if (tipo === 'mes') {
                inputMes.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>