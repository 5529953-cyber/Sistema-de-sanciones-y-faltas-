<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * SISFAL - Controlador de Autenticación
 */
require_once __DIR__ . '/../../config/database.php';

class AuthController {

    public static function iniciarSesion(): void {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $usuario   = trim($data['usuario']  ?? '');
        $contrasena = trim($data['contrasena'] ?? '');

        if ($usuario === '' || $contrasena === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Completa todos los campos.']);
            return;
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, nombre, apellido, contrasena, rol
               FROM usuarios
              WHERE usuario = :u AND activo = 1
              LIMIT 1"
        );
        $stmt->execute([':u' => $usuario]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($contrasena, $user['contrasena'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Usuario o contraseña incorrectos.']);
            return;
        }

        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['nombre']    = $user['nombre'] . ' ' . $user['apellido'];
        $_SESSION['rol']       = $user['rol'];

        echo json_encode([
            'ok'     => true,
            'nombre' => $_SESSION['nombre'],
            'rol'    => $user['rol'],
        ]);
    }

    public static function cerrarSesion(): void {
        session_start();
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public static function verificarSesion(): void {
        session_start();
        header('Content-Type: application/json');
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'autenticado' => true,
                'nombre'      => $_SESSION['nombre'],
                'rol'         => $_SESSION['rol'],
            ]);
        } else {
            echo json_encode(['autenticado' => false]);
        }
    }
}

// Router
$action = $_GET['action'] ?? '';
match ($action) {
    'login'   => AuthController::iniciarSesion(),
    'logout'  => AuthController::cerrarSesion(),
    'check'   => AuthController::verificarSesion(),
    default   => http_response_code(404),
};
