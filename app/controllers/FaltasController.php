<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * SISFAL - Controlador de Faltas
 */
require_once __DIR__ . '/../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['error'=>'No autorizado.']); exit;
}
header('Content-Type: application/json');

class FaltasController {
    private PDO $pdo;
    public function __construct() { $this->pdo = Database::getInstance()->getConnection(); }

    public function listar(): void {
        $stmt = $this->pdo->prepare(
            "SELECT f.id, e.nie, CONCAT(e.nombre,' ',e.apellido) AS estudiante,
                    tf.nombre AS tipo_falta, tf.gravedad, f.descripcion, f.fecha,
                    CONCAT(u.nombre,' ',u.apellido) AS registrado_por
               FROM faltas f
               JOIN estudiantes e  ON e.id = f.estudiante_id
               JOIN tipos_falta tf ON tf.id = f.tipo_falta_id
               JOIN usuarios u     ON u.id  = f.registrado_por
              ORDER BY f.fecha DESC LIMIT 500"
        );
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
    }
    // GET /api/faltas.php?action=por_estudiante&estudiante_id=X
    public function porEstudiante(): void {
        $id = (int) ($_GET['estudiante_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT f.id, tf.nombre AS tipo_falta, tf.gravedad, f.descripcion, f.fecha,
                    CONCAT(u.nombre,' ',u.apellido) AS registrado_por,
                    s.id AS sancion_id, s.tipo_sancion AS sancion_tipo, s.estado AS sancion_estado
               FROM faltas f
               JOIN tipos_falta tf ON tf.id = f.tipo_falta_id
               JOIN usuarios u     ON u.id  = f.registrado_por
               LEFT JOIN sanciones s ON s.falta_id = f.id
              WHERE f.estudiante_id = :id
              ORDER BY f.fecha DESC"
        );
        $stmt->execute([':id' => $id]);
        echo json_encode($stmt->fetchAll());
    }

    public function tiposFalta(): void {
        $stmt = $this->pdo->query("SELECT * FROM tipos_falta ORDER BY gravedad, nombre");
        echo json_encode($stmt->fetchAll());
    }

    public function crear(): void {
        $d = json_decode(file_get_contents('php://input'), true);
        foreach (['estudiante_id','tipo_falta_id','fecha'] as $c) {
            if (empty($d[$c])) { http_response_code(400); echo json_encode(['error'=>"$c requerido"]); return; }
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO faltas (estudiante_id, tipo_falta_id, descripcion, fecha, registrado_por)
             VALUES (:eid, :tid, :desc, :fecha, :uid)"
        );
        $stmt->execute([
            ':eid'  => (int)$d['estudiante_id'],
            ':tid'  => (int)$d['tipo_falta_id'],
            ':desc' => $d['descripcion'] ?? null,
            ':fecha'=> $d['fecha'],
            ':uid'  => $_SESSION['user_id'],
        ]);

        // Notificación automática por correo al estudiante (sin sección de sanciones).
        // No debe bloquear ni fallar el registro de la falta si el envío falla.
        define('SISFAL_REPORTES_INCLUDE_ONLY', true);
        require_once __DIR__ . '/ReportesController.php';
        (new ReportesController())->enviarNotificacionFalta((int)$d['estudiante_id']);

        echo json_encode(['ok'=>true, 'id'=>$this->pdo->lastInsertId()]);
    }

    public function eliminar(): void {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("DELETE FROM faltas WHERE id = :id");
        $stmt->execute([':id'=>$id]);
        echo json_encode(['ok'=>true]);
    }
}

$ctrl   = new FaltasController();
$action = $_GET['action'] ?? '';
match ($action) {
    'listar'         => $ctrl->listar(),
    'por_estudiante' => $ctrl->porEstudiante(),
    'tipos'          => $ctrl->tiposFalta(),
    'crear'          => $ctrl->crear(),
    'eliminar'       => $ctrl->eliminar(),
    default          => http_response_code(404),
};