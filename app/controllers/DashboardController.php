<?php
/**
 * SISFAL - Controlador del Dashboard
 */
require_once __DIR__ . '/../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['error'=>'No autorizado.']); exit;
}
header('Content-Type: application/json');

$pdo = Database::getInstance()->getConnection();

$stats = [];

// Totales
$stats['estudiantes']    = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE activo=1")->fetchColumn();
$stats['faltas_mes']     = $pdo->query("SELECT COUNT(*) FROM faltas WHERE MONTH(fecha)=MONTH(NOW()) AND YEAR(fecha)=YEAR(NOW())")->fetchColumn();
$stats['sanciones_activas'] = $pdo->query("SELECT COUNT(*) FROM sanciones WHERE estado='activa'")->fetchColumn();
$stats['faltas_total']   = $pdo->query("SELECT COUNT(*) FROM faltas")->fetchColumn();

// Faltas por gravedad
$stmt = $pdo->query(
    "SELECT tf.gravedad, COUNT(*) AS total
       FROM faltas f JOIN tipos_falta tf ON tf.id=f.tipo_falta_id
      GROUP BY tf.gravedad"
);
$stats['por_gravedad'] = $stmt->fetchAll();

// Últimas 5 faltas
$stmt = $pdo->query(
    "SELECT CONCAT(e.nombre,' ',e.apellido) AS estudiante, tf.nombre AS tipo, f.fecha
       FROM faltas f
       JOIN estudiantes e  ON e.id=f.estudiante_id
       JOIN tipos_falta tf ON tf.id=f.tipo_falta_id
      ORDER BY f.creado_en DESC LIMIT 5"
);
$stats['ultimas_faltas'] = $stmt->fetchAll();

echo json_encode($stats);
