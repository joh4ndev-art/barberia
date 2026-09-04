<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado.");
}

require_once 'conexion.php';

$mi_id = $_SESSION['usuario_id'];
$mi_rol = strtolower(trim($_SESSION['usuario_rol'] ?? 'barbero'));
$es_admin = ($mi_rol === 'admin');

// Capturar parámetros
$tipo_filtro = $_GET['tipo_filtro'] ?? 'mes';
$fecha_dia = $_GET['fecha_dia'] ?? date('Y-m-d');
$fecha_semana = $_GET['fecha_semana'] ?? date('Y-\WW');
$fecha_mes = $_GET['fecha_mes'] ?? date('Y-m');

$barbero_filtro = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : ($es_admin ? 0 : $mi_id);
if (!$es_admin) {
    $barbero_filtro = $mi_id;
}

$where_filtro = "";
$params = [];

if ($barbero_filtro > 0) {
    $where_filtro .= " AND c.barbero_id = :barbero_id";
    $params[':barbero_id'] = $barbero_filtro;
}

if ($tipo_filtro === 'dia') {
    $where_filtro .= " AND DATE(c.fecha) = :fecha_dia";
    $params[':fecha_dia'] = $fecha_dia;
} elseif ($tipo_filtro === 'semana') {
    list($anio, $semana) = explode('-W', $fecha_semana);
    $dto = new DateTime();
    $dto->setISODate((int)$anio, (int)$semana);
    $where_filtro .= " AND DATE(c.fecha) BETWEEN :inicio_semana AND :fin_semana";
    $params[':inicio_semana'] = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $params[':fin_semana'] = $dto->format('Y-m-d');
} else {
    $where_filtro .= " AND DATE_FORMAT(c.fecha, '%Y-%m') = :fecha_mes";
    $params[':fecha_mes'] = $fecha_mes;
}

$sql = "
    SELECT 
        c.fecha, 
        c.hora, 
        COALESCE(u.nombre, 'Cliente General') AS cliente,
        COALESCE(b.nombre, 'Sin asignar') AS barbero,
        c.servicio, 
        c.precio
    FROM citas c
    LEFT JOIN usuarios u ON c.cliente_id = u.id
    LEFT JOIN usuarios b ON c.barbero_id = b.id
    WHERE c.estado = 'finalizada'
      $where_filtro
    ORDER BY c.fecha DESC, c.hora DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar cabeceras de descarga de archivo CSV
$filename = "Reporte_Ganancias_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Carácter BOM para soporte UTF-8 en Excel (acentos y símbolos)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeceras de columnas
fputcsv($output, ['Fecha', 'Hora', 'Cliente', 'Barbero', 'Servicio', 'Valor (COP)'], ';');

$total = 0;
foreach ($filas as $f) {
    $precio = floatval($f['precio'] ?? 0);
    $total += $precio;
    fputcsv($output, [
        $f['fecha'],
        date('h:i A', strtotime($f['hora'])),
        $f['cliente'],
        $f['barbero'],
        $f['servicio'] ?? 'Corte General',
        $precio
    ], ';');
}

// Separador y Fila del Total General
fputcsv($output, [], ';');
fputcsv($output, ['TOTAL GANANCIAS', '', '', '', '', $total], ';');

fclose($output);
exit();
?>