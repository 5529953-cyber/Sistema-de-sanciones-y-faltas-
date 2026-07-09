<?php
/**
 * SISFAL - Controlador de Sanciones
 */
require_once 'C:/wamp64/www/sisfal/config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['error'=>'No autorizado.']); exit;
}
header('Content-Type: application/json');

class SancionesController {
    private PDO $pdo;
    public function __construct() { $this->pdo = Database::getInstance()->getConnection(); }

    public function listar(): void {
    $stmt = $this->pdo->prepare(
        "SELECT s.id, s.estudiante_id, CONCAT(e.nombre,' ',e.apellido) AS estudiante, e.nie,
                s.tipo_sancion, s.descripcion, s.fecha_inicio, s.fecha_fin, s.estado,
                tf.nombre AS tipo_falta, s.falta_id
           FROM sanciones s
           JOIN estudiantes e  ON e.id = s.estudiante_id
           JOIN faltas f       ON f.id = s.falta_id
           JOIN tipos_falta tf ON tf.id = f.tipo_falta_id
          ORDER BY s.creado_en DESC LIMIT 500"
    );
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
}

    public function crear(): void {
        $d = json_decode(file_get_contents('php://input'), true);
        foreach (['falta_id','estudiante_id','tipo_sancion','fecha_inicio'] as $c) {
            if (empty($d[$c])) { http_response_code(400); echo json_encode(['error'=>"$c requerido"]); return; }
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO sanciones (falta_id, estudiante_id, tipo_sancion, descripcion,
                                    fecha_inicio, fecha_fin, registrado_por)
             VALUES (:fid,:eid,:tipo,:desc,:fi,:ff,:uid)"
        );
        $stmt->execute([
            ':fid'  => (int)$d['falta_id'],
            ':eid'  => (int)$d['estudiante_id'],
            ':tipo' => $d['tipo_sancion'],
            ':desc' => $d['descripcion'] ?? null,
            ':fi'   => $d['fecha_inicio'],
            ':ff'   => $d['fecha_fin'] ?? null,
            ':uid'  => $_SESSION['user_id'],
        ]);
        echo json_encode(['ok'=>true, 'id'=>$this->pdo->lastInsertId()]);
    }

    public function actualizarEstado(): void {
        $id    = (int)($_GET['id'] ?? 0);
        $d     = json_decode(file_get_contents('php://input'), true);
        $estado= $d['estado'] ?? 'cumplida';
        $stmt  = $this->pdo->prepare("UPDATE sanciones SET estado=:e WHERE id=:id");
        $stmt->execute([':e'=>$estado, ':id'=>$id]);
        echo json_encode(['ok'=>true]);
    }
}

$ctrl   = new SancionesController();
$action = $_GET['action'] ?? '';
match ($action) {
    'listar'          => $ctrl->listar(),
    'crear'           => $ctrl->crear(),
    'actualizar_estado'=> $ctrl->actualizarEstado(),
    default           => http_response_code(404),
};
