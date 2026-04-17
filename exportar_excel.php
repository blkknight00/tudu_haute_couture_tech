<?php
require_once 'config.php';
session_start();

// Incluir el autoloader de Composer
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Seguridad: Verificar login
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}
 
$usuario_actual_id = $_SESSION['usuario_id'];
 
// 1. OBTENER FILTROS (igual que en dashboard.php)
$proyecto_id = isset($_GET['proyecto_id']) ? $_GET['proyecto_id'] : null;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_usuario = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : null;
$filtro_vista = isset($_GET['vista']) ? $_GET['vista'] : 'publico';
 
// 2. CONSTRUIR LA CONSULTA SQL (copiada y adaptada de dashboard.php)
$sql = "SELECT t.*, p.nombre as proyecto_nombre
        FROM tareas t 
        JOIN proyectos p ON t.proyecto_id = p.id 
        WHERE ";
$params = [];
 
if ($filtro_vista == 'personal') {
    $sql .= "t.visibility = 'private' AND t.usuario_id = ?";
    $params[] = $usuario_actual_id;
} else {
    $sql .= "t.visibility = 'public'";
}
 
if ($proyecto_id) {
    $sql .= " AND t.proyecto_id = ?";
    $params[] = $proyecto_id;
}
 
if ($filtro_estado != 'todos') {
    $sql .= " AND t.estado = ? ";
    $params[] = $filtro_estado;
}
 
if ($filtro_usuario) {
    $sql .= " AND EXISTS (SELECT 1 FROM tarea_asignaciones ta_f WHERE ta_f.tarea_id = t.id AND ta_f.usuario_id = ?)";
    $params[] = $filtro_usuario;
}
 
$sql .= " AND t.estado != 'archivado' ORDER BY t.fecha_creacion DESC";
 
$stmt_tareas = $pdo->prepare($sql);
$stmt_tareas->execute($params);
$tareas = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
 
// 3. OBTENER DATOS ADICIONALES (Asignaciones y Etiquetas)
$asignaciones_por_tarea = [];
$etiquetas_por_tarea = [];
if (!empty($tareas)) {
    $tarea_ids = array_column($tareas, 'id');
    $placeholders = implode(',', array_fill(0, count($tarea_ids), '?'));
 
    $sql_asig = "SELECT ta.tarea_id, u.nombre FROM tarea_asignaciones ta JOIN usuarios u ON ta.usuario_id = u.id WHERE ta.tarea_id IN ($placeholders)";
    $stmt_asig = $pdo->prepare($sql_asig);
    $stmt_asig->execute($tarea_ids);
    foreach ($stmt_asig->fetchAll(PDO::FETCH_ASSOC) as $asig) {
        $asignaciones_por_tarea[$asig['tarea_id']][] = $asig['nombre'];
    }
 
    $sql_tags = "SELECT te.tarea_id, e.nombre FROM tarea_etiquetas te JOIN etiquetas e ON te.etiqueta_id = e.id WHERE te.tarea_id IN ($placeholders)";
    $stmt_tags = $pdo->prepare($sql_tags);
    $stmt_tags->execute($tarea_ids);
    foreach ($stmt_tags->fetchAll(PDO::FETCH_ASSOC) as $tag) {
        $etiquetas_por_tarea[$tag['tarea_id']][] = $tag['nombre'];
    }
}
 
// 4. GENERAR EL ARCHIVO XLSX
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte de Tareas');
 
// Set headers
$headers = ['ID Tarea', 'Titulo', 'Descripcion', 'Proyecto', 'Estado', 'Asignado a', 'Etiquetas', 'Fecha Creacion', 'Fecha Termino'];
$sheet->fromArray($headers, NULL, 'A1');
 
// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3e5973']] // Usando el color del header
];
$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
 
// Populate data
$row = 2;
foreach ($tareas as $tarea) {
    $asignados_str = isset($asignaciones_por_tarea[$tarea['id']]) ? implode(', ', $asignaciones_por_tarea[$tarea['id']]) : '';
    $etiquetas_str = isset($etiquetas_por_tarea[$tarea['id']]) ? implode(', ', $etiquetas_por_tarea[$tarea['id']]) : '';
    
    $sheet->fromArray([
        $tarea['id'], $tarea['titulo'], $tarea['descripcion'], $tarea['proyecto_nombre'], 
        ucfirst(str_replace('_', ' ', $tarea['estado'])), $asignados_str, $etiquetas_str, 
        date('Y-m-d H:i', strtotime($tarea['fecha_creacion'])), 
        $tarea['fecha_termino'] ? date('Y-m-d', strtotime($tarea['fecha_termino'])) : ''
    ], NULL, 'A' . $row);
    
    $row++;
}
 
// Auto-size columns
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
 
// 5. ENVIAR AL NAVEGADOR
$filename = "reporte_tareas_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
 
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>