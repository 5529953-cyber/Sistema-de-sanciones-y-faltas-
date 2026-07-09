<?php
/**
 * SISFAL - Controlador de Reportes
 * Envío de reportes disciplinarios por correo electrónico y generación de PDF
 */
require_once 'C:/wamp64/www/sisfal/config/database.php';
require_once 'C:/wamp64/www/sisfal/config/Mail.php';
require_once 'C:/wamp64/www/sisfal/lib/PHPMailer/Exception.php';
require_once 'C:/wamp64/www/sisfal/lib/PHPMailer/PHPMailer.php';
require_once 'C:/wamp64/www/sisfal/lib/PHPMailer/SMTP.php';
require_once 'C:/wamp64/www/sisfal/app/controllers/pdf/ReportePDF.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cuando este archivo se incluye desde otro controlador (ej. envío automático
// de correo al registrar una falta) se define esta constante ANTES del
// require para reutilizar la clase sin repetir session_start()/headers.
if (!defined('SISFAL_REPORTES_INCLUDE_ONLY')) {
    session_start();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado.']);
        exit;
    }

    header('Content-Type: application/json');
}

class ReportesController {
    private PDO $pdo;

    private const ESPECIALIDADES = [
        'MA'   => 'Mantenimiento Automotriz',
        'DS'   => 'Desarrollo de Software',
        'MI'   => 'Mecanica Industrial',
        'ITSI' => 'Infraestructura Tecnologica y Sistemas Informaticos',
        'ECA'  => 'Electronica',
        'SE'   => 'Sistemas Electricos',
    ];

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // POST /api/reportes.php?action=enviar_correo   body: { estudiante_id }
    public function enviarCorreo(): void {
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($d['estudiante_id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'estudiante_id es requerido.']);
            return;
        }

        $est = $this->obtenerEstudiante($id);
        if (!$est) {
            http_response_code(404);
            echo json_encode(['error' => 'Estudiante no encontrado.']);
            return;
        }
        if (empty($est['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El estudiante no tiene un correo registrado.']);
            return;
        }

        $faltas    = $this->obtenerFaltas($id);
        $sanciones = $this->obtenerSanciones($id);
        $cuerpo    = $this->construirCuerpo($est, $faltas, $sanciones);

        // Generar el PDF individual en memoria para adjuntarlo al correo
        $pdf         = $this->construirPdfEstudiante($est, $faltas, $sanciones);
        $pdfContenido = $pdf->Output('S'); // 'S' = devolver como string, no imprimir
        $pdfNombre    = 'reporte_' . $est['nie'] . '.pdf';

        try {
            $this->enviar(
                $est['email'],
                "Reporte disciplinario - {$est['nombre']} {$est['apellido']}",
                $cuerpo,
                $pdfContenido,
                $pdfNombre
            );
            echo json_encode(['ok' => true, 'enviado_a' => $est['email']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo enviar el correo: ' . $e->getMessage()]);
        }
    }

    // Construye el objeto PDF del reporte individual (usado tanto para descarga como para adjuntar al correo)
    // $incluirSanciones = false: se usa en la notificación automática al registrar una falta,
    // donde todavía no aplica ninguna sanción.
    private function construirPdfEstudiante(array $est, array $faltas, array $sanciones, bool $incluirSanciones = true): ReportePDF {
        $c = $this->parsearCodigo($est['codigo']);

        $pdf = new ReportePDF();
        $pdf->tituloReporte = $incluirSanciones ? 'REPORTE DISCIPLINARIO INDIVIDUAL' : 'NOTIFICACION DE FALTA';
        $pdf->numeroReporte = 'RIND-' . date('Y') . '-' . str_pad((string) $est['id'], 5, '0', STR_PAD_LEFT);
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->tituloConLineas('Informacion del estudiante');
        $pdf->filaDatoEstudiante('Nombre', $est['nombre'] . ' ' . $est['apellido']);
        $pdf->filaDatoEstudiante('NIE', $est['nie']);
        $pdf->filaDatoEstudiante('Codigo', $est['codigo']);
        $pdf->filaDatoEstudiante('Especialidad', $c['especialidad_nombre'] ?: '-');
        $pdf->filaDatoEstudiante('Grado y Seccion', ($c['anio'] && $c['seccion']) ? ($c['anio'] . 'ro/o "' . $c['seccion'] . '"') : '-');
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(20, 83, 45);
        $pdf->Cell(0, 7, $pdf->t('FALTAS REGISTRADAS'), 0, 1);
        $pdf->tablaFaltas($faltas);

        if ($incluirSanciones) {
            $pdf->Ln(6);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(20, 83, 45);
            $pdf->Cell(0, 7, $pdf->t('SANCIONES APLICADAS'), 0, 1);
            $pdf->tablaSanciones($sanciones);
        }

        $pdf->dibujarFirmas();

        return $pdf;
    }

    // GET /api/reportes.php?action=pdf_estudiante&id=X
    public function pdfEstudiante(): void {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'id es requerido.']);
            return;
        }
        $est = $this->obtenerEstudiante($id);
        if (!$est) {
            http_response_code(404);
            echo json_encode(['error' => 'Estudiante no encontrado.']);
            return;
        }

        $faltas    = $this->obtenerFaltas($id);
        $sanciones = $this->obtenerSanciones($id);
        $pdf       = $this->construirPdfEstudiante($est, $faltas, $sanciones);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte_' . $est['nie'] . '.pdf"');
        $pdf->Output('I', 'reporte_' . $est['nie'] . '.pdf');
    }

    // GET /api/reportes.php?action=pdf_seccion&codigo=DS3A
    public function pdfSeccion(): void {
        $codigo = strtoupper(trim($_GET['codigo'] ?? ''));
        if (!$codigo) {
            http_response_code(400);
            echo json_encode(['error' => 'codigo es requerido.']);
            return;
        }

        $estudiantes = $this->obtenerEstudiantesPorCodigo($codigo);
        if (!$estudiantes) {
            http_response_code(404);
            echo json_encode(['error' => 'No hay estudiantes activos con ese codigo.']);
            return;
        }

        $filas = [];
        $totalFaltas = 0;
        $totalSancionesActivas = 0;
        $estudiantesSinSanciones = 0;
        $fechaMasAntigua = null;

        foreach ($estudiantes as $est) {
            $faltas    = $this->obtenerFaltas($est['id']);
            $sanciones = $this->obtenerSanciones($est['id']);
            $activas   = array_values(array_filter($sanciones, fn($s) => $s['estado'] === 'activa'));
            $tipos     = array_values(array_unique(array_column($faltas, 'tipo_falta')));
            $tiposSanc = array_values(array_unique(array_map('ucfirst', array_column($sanciones, 'tipo_sancion'))));

            foreach ($faltas as $f) {
                if ($fechaMasAntigua === null || $f['fecha'] < $fechaMasAntigua) {
                    $fechaMasAntigua = $f['fecha'];
                }
            }

            $filas[] = [
                'nie'       => $est['nie'],
                'nombre'    => $est['nombre'] . ' ' . $est['apellido'],
                'codigo'    => $est['codigo'],
                'tipos'     => $tipos,
                'sanciones' => $tiposSanc,
                'activo'    => $est['activo'],
            ];

            $totalFaltas += count($faltas);
            $totalSancionesActivas += count($activas);
            if (!count($activas)) $estudiantesSinSanciones++;
        }

        $promedio = count($estudiantes) ? round($totalFaltas / count($estudiantes), 1) : 0;
        $c        = $this->parsearCodigo($codigo);
        $numGrupo = str_pad((string) (crc32($codigo) % 100000), 5, '0', STR_PAD_LEFT);

        $pdf = new ReportePDF();
        $pdf->tituloReporte = 'REPORTE DISCIPLINARIO DE GRUPO';
        $pdf->subtitulo     = 'Resumen de faltas y sanciones de estudiantes';
        $pdf->numeroReporte = 'RGR-' . date('Y') . '-' . $numGrupo;
        $pdf->SetAutoPageBreak(false);
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->bloqueInfoSeccion($c['especialidad_nombre'], $c['anio'], $c['seccion'], $fechaMasAntigua, count($estudiantes));
        $pdf->tablaResumenSeccion($filas);
        $pdf->bloqueTotales($totalFaltas, $promedio, $totalSancionesActivas, $estudiantesSinSanciones);
        $pdf->dibujarFirmas();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte_grupo_' . $codigo . '.pdf"');
        $pdf->Output('I', 'reporte_grupo_' . $codigo . '.pdf');
    }

    private function obtenerEstudiante(int $id): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM estudiantes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    private function obtenerEstudiantesPorCodigo(string $codigo): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM estudiantes WHERE codigo = :codigo AND activo = 1 ORDER BY apellido, nombre"
        );
        $stmt->execute([':codigo' => $codigo]);
        return $stmt->fetchAll();
    }

    // Interpreta el campo "codigo" (ej: "DS3A") igual que parsearCodigo() en app.js
    private function parsearCodigo(string $codigo): array {
        if (preg_match('/^([A-Za-z]+)(\d)([A-Za-z]+)$/', trim($codigo), $m)) {
            $prefijo = strtoupper($m[1]);
            return [
                'especialidad_prefijo' => $prefijo,
                'especialidad_nombre'  => self::ESPECIALIDADES[$prefijo] ?? $prefijo,
                'anio'                 => $m[2],
                'seccion'              => strtoupper($m[3]),
            ];
        }
        return ['especialidad_prefijo' => '', 'especialidad_nombre' => '', 'anio' => '', 'seccion' => ''];
    }

    private function obtenerFaltas(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT tf.nombre AS tipo_falta, tf.gravedad, f.descripcion, f.fecha
               FROM faltas f
               JOIN tipos_falta tf ON tf.id = f.tipo_falta_id
              WHERE f.estudiante_id = :id
              ORDER BY f.fecha DESC"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    private function obtenerSanciones(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT tipo_sancion, descripcion, fecha_inicio, fecha_fin, estado
               FROM sanciones
              WHERE estudiante_id = :id
              ORDER BY fecha_inicio DESC"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    private function construirCuerpo(array $est, array $faltas, array $sanciones, bool $incluirSanciones = true): string {
        $filasFaltas = '';
        foreach ($faltas as $f) {
            $filasFaltas .= "<tr>
                <td style='padding:6px;border:1px solid #ddd'>{$f['fecha']}</td>
                <td style='padding:6px;border:1px solid #ddd'>" . htmlspecialchars($f['tipo_falta']) . "</td>
                <td style='padding:6px;border:1px solid #ddd'>" . htmlspecialchars($f['gravedad']) . "</td>
                <td style='padding:6px;border:1px solid #ddd'>" . htmlspecialchars($f['descripcion'] ?? '-') . "</td>
            </tr>";
        }
        if (!$filasFaltas) $filasFaltas = "<tr><td colspan='4' style='padding:6px;text-align:center'>Sin faltas registradas</td></tr>";

        $bloqueSanciones = '';
        if ($incluirSanciones) {
            $filasSanciones = '';
            foreach ($sanciones as $s) {
                $filasSanciones .= "<tr>
                    <td style='padding:6px;border:1px solid #ddd'>" . htmlspecialchars($s['tipo_sancion']) . "</td>
                    <td style='padding:6px;border:1px solid #ddd'>{$s['fecha_inicio']} - " . ($s['fecha_fin'] ?? '—') . "</td>
                    <td style='padding:6px;border:1px solid #ddd'>" . htmlspecialchars($s['estado']) . "</td>
                </tr>";
            }
            if (!$filasSanciones) $filasSanciones = "<tr><td colspan='3' style='padding:6px;text-align:center'>Sin sanciones registradas</td></tr>";

            $bloqueSanciones = "
          <h4 style='margin-top:20px'>Sanciones aplicadas</h4>
          <table style='border-collapse:collapse;width:100%'>
            <tr style='background:#f0f0f0'>
              <th style='padding:6px;border:1px solid #ddd'>Tipo</th>
              <th style='padding:6px;border:1px solid #ddd'>Periodo</th>
              <th style='padding:6px;border:1px solid #ddd'>Estado</th>
            </tr>
            {$filasSanciones}
          </table>";
        }

        $nombreCompleto = htmlspecialchars($est['nombre'] . ' ' . $est['apellido']);
        $titulo         = $incluirSanciones ? 'Reporte disciplinario' : 'Notificación de falta registrada';

        return "
        <div style='font-family:Arial,sans-serif;color:#222'>
          <h2 style='color:#1a3a6b'>Instituto Nacional Técnico Industrial</h2>
          <h3>{$titulo}</h3>
          <p><strong>Estudiante:</strong> {$nombreCompleto}<br>
             <strong>NIE:</strong> {$est['nie']}<br>
             <strong>Código:</strong> {$est['codigo']}</p>

          <h4>Faltas registradas</h4>
          <table style='border-collapse:collapse;width:100%'>
            <tr style='background:#f0f0f0'>
              <th style='padding:6px;border:1px solid #ddd'>Fecha</th>
              <th style='padding:6px;border:1px solid #ddd'>Tipo</th>
              <th style='padding:6px;border:1px solid #ddd'>Gravedad</th>
              <th style='padding:6px;border:1px solid #ddd'>Descripción</th>
            </tr>
            {$filasFaltas}
          </table>
          {$bloqueSanciones}

          <p style='margin-top:20px;font-size:.85rem;color:#666'>
            Este es un reporte automático generado por SISFAL.
          </p>
        </div>";
    }

    // Llamado desde FaltasController justo después de registrar una falta.
    // Envía la notificación al correo del estudiante con el PDF adjunto,
    // SIN sección de sanciones (todavía no aplica ninguna en este punto).
    // No lanza excepciones hacia afuera: si falla, solo se registra en el log
    // para no bloquear el registro de la falta.
    public function enviarNotificacionFalta(int $estudianteId): void {
        try {
            $est = $this->obtenerEstudiante($estudianteId);
            if (!$est || empty($est['email'])) {
                return; // sin correo registrado, no hay a quién notificar
            }

            $faltas = $this->obtenerFaltas($estudianteId);
            $cuerpo = $this->construirCuerpo($est, $faltas, [], false);

            $pdf          = $this->construirPdfEstudiante($est, $faltas, [], false);
            $pdfContenido = $pdf->Output('S');
            $pdfNombre    = 'notificacion_falta_' . $est['nie'] . '.pdf';

            $this->enviar(
                $est['email'],
                "Notificación de falta - {$est['nombre']} {$est['apellido']}",
                $cuerpo,
                $pdfContenido,
                $pdfNombre
            );
        } catch (Throwable $e) {
            error_log('SISFAL - Error al enviar notificación automática de falta: ' . $e->getMessage());
        }
    }

    private function enviar(
        string $destino,
        string $asunto,
        string $cuerpoHtml,
        ?string $adjuntoContenido = null,
        ?string $adjuntoNombre = null
    ): void {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($destino);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;

        if ($adjuntoContenido !== null && $adjuntoNombre !== null) {
            $mail->addStringAttachment($adjuntoContenido, $adjuntoNombre, 'base64', 'application/pdf');
        }

        $mail->send();
    }
}

if (!defined('SISFAL_REPORTES_INCLUDE_ONLY')) {
    $ctrl   = new ReportesController();
    $action = $_GET['action'] ?? '';

    try {
        match ($action) {
            'enviar_correo'  => $ctrl->enviarCorreo(),
            'pdf_estudiante' => $ctrl->pdfEstudiante(),
            'pdf_seccion'    => $ctrl->pdfSeccion(),
            default          => (function () {
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
}