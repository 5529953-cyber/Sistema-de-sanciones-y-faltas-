<?php
/**
 * SISFAL - Controlador de Estudiantes
 * Instituto Nacional Técnico Industrial
 */
require_once 'C:/wamp64/www/sisfal/config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

header('Content-Type: application/json');

class EstudiantesController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // GET /api/estudiantes.php?action=listar[&q=busqueda]
    // NOTA: se agregó telefono y email al SELECT para que la tabla de
    // Estudiantes pueda mostrar esas columnas (antes solo se usaban en el modal).
    // También se agregó la búsqueda por email, tal como aparece en el
    // placeholder del buscador de la interfaz nueva.
    public function listar(): void {
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $this->pdo->prepare(
            "SELECT id, nie, nombre, apellido, codigo, telefono, email, activo
               FROM estudiantes
              WHERE (nombre LIKE :q1 OR apellido LIKE :q2 OR nie LIKE :q3
                     OR codigo LIKE :q4 OR email LIKE :q5)
              ORDER BY apellido, nombre
              LIMIT 500"
        );
        $stmt->execute([':q1' => $q, ':q2' => $q, ':q3' => $q, ':q4' => $q, ':q5' => $q]);
        echo json_encode($stmt->fetchAll());
    }

    // GET /api/estudiantes.php?action=ver&id=X
    public function ver(): void {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM estudiantes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error' => 'No encontrado']); return; }
        echo json_encode($row);
    }

    // POST /api/estudiantes.php?action=crear
    public function crear(): void {
        $d = json_decode(file_get_contents('php://input'), true);
        $this->validar($d);
        $stmt = $this->pdo->prepare(
            "INSERT INTO estudiantes (nie, nombre, apellido, codigo, fecha_nac, telefono, email)
             VALUES (:nie, :nombre, :apellido, :codigo, :fecha_nac, :telefono, :email)"
        );
        $stmt->execute([
            ':nie'      => trim($d['nie']),
            ':nombre'   => ucwords(strtolower(trim($d['nombre']))),
            ':apellido' => ucwords(strtolower(trim($d['apellido']))),
            ':codigo'   => strtoupper(trim($d['codigo'])),
            ':fecha_nac'=> $d['fecha_nac'] ?? null,
            ':telefono' => $d['telefono']  ?? null,
            ':email'    => $d['email']     ?? null,
        ]);
        echo json_encode(['ok' => true, 'id' => $this->pdo->lastInsertId()]);
    }

    // PUT /api/estudiantes.php?action=actualizar&id=X
    public function actualizar(): void {
        $id = (int) ($_GET['id'] ?? 0);
        $d  = json_decode(file_get_contents('php://input'), true);
        $this->validar($d);
        $stmt = $this->pdo->prepare(
            "UPDATE estudiantes
                SET nie=:nie, nombre=:nombre, apellido=:apellido,
                    codigo=:codigo, fecha_nac=:fecha_nac,
                    telefono=:telefono, email=:email
              WHERE id = :id"
        );
        $stmt->execute([
            ':nie'      => trim($d['nie']),
            ':nombre'   => ucwords(strtolower(trim($d['nombre']))),
            ':apellido' => ucwords(strtolower(trim($d['apellido']))),
            ':codigo'   => strtoupper(trim($d['codigo'])),
            ':fecha_nac'=> $d['fecha_nac'] ?? null,
            ':telefono' => $d['telefono']  ?? null,
            ':email'    => $d['email']     ?? null,
            ':id'       => $id,
        ]);
        echo json_encode(['ok' => true]);
    }

    // DELETE /api/estudiantes.php?action=eliminar&id=X
    public function eliminar(): void {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("UPDATE estudiantes SET activo = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
    }

    private function validar(array $d): void {
        foreach (['nie', 'nombre', 'apellido', 'codigo'] as $campo) {
            if (empty(trim($d[$campo] ?? ''))) {
                http_response_code(400);
                echo json_encode(['error' => "El campo $campo es requerido."]);
                exit;
            }
        }
        if (!preg_match('/^\d{7}$/', trim($d['nie']))) {
            http_response_code(400);
            echo json_encode(['error' => 'El NIE debe contener exactamente 7 digitos numericos.']);
            exit;
        }
    }
}

$ctrl   = new EstudiantesController();
$action = $_GET['action'] ?? '';

try {
    match (true) {
        $action === 'listar'     => $ctrl->listar(),
        $action === 'ver'        => $ctrl->ver(),
        $action === 'crear'      => $ctrl->crear(),
        $action === 'actualizar' => $ctrl->actualizar(),
        $action === 'eliminar'   => $ctrl->eliminar(),
        default                  => (function () {
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada.']);
        })(),
    };
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
