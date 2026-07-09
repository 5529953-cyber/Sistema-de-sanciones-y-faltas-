<?php
/**
 * SISFAL - Clase base para generar reportes PDF
 * Extiende FPDF agregando membrete institucional, encabezado y pie de página.
 */
require_once 'C:/wamp64/www/sisfal/lib/FPDF/fpdf.php';

class ReportePDF extends FPDF {

    public string $tituloReporte = 'Reporte disciplinario';
    public string $subtitulo     = '';   // Subtitulo opcional bajo el titulo (solo reporte de grupo)
    public string $numeroReporte = '';

    private const MESES = [
        1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
        7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre',
    ];

    /** Convierte UTF-8 (lo que devuelve MySQL) a Latin-1 (lo que entiende FPDF) */
    public function t(?string $s): string {
        return mb_convert_encoding((string)($s ?? ''), 'ISO-8859-1', 'UTF-8');
    }

    public function fechaEmisionTexto(): string {
        $d = (int) date('j'); $m = (int) date('n'); $y = date('Y');
        return "{$d} de " . self::MESES[$m] . " de {$y}";
    }

    // Se ejecuta automaticamente al agregar cada pagina
    function Header() {
        $this->SetY(10);

        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(20, 83, 45);
        $this->Cell(120, 6, $this->t('Instituto Nacional Tecnico Industrial'), 0, 0, 'L');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(70, 6, $this->t('Fecha de emision: ' . $this->fechaEmisionTexto()), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(120, 5, $this->t('Disciplina, Respeto y Excelencia'), 0, 0, 'L');

        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(20, 83, 45);
        $this->Cell(70, 5, $this->t('Reporte N: ' . $this->numeroReporte), 0, 1, 'R');

        $this->SetDrawColor(20, 83, 45);
        $this->SetLineWidth(0.6);
        $this->Line(10, $this->GetY() + 2, 200, $this->GetY() + 2);
        $this->SetLineWidth(0.2);
        $this->Ln(7);

        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(20, 83, 45);
        $this->Cell(0, 8, $this->t($this->tituloReporte), 0, 1, 'C');

        if ($this->subtitulo) {
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(110, 110, 110);
            $this->Cell(0, 6, $this->t($this->subtitulo), 0, 1, 'C');
        }
        $this->Ln(4);
    }

    // Se ejecuta automaticamente al final de cada pagina
    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 6, $this->t('SISFAL - Pagina ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }

    // Divisor de seccion con lineas a los lados: ---- Texto ----
    public function tituloConLineas(string $texto): void {
        $texto = $this->t($texto);
        $y = $this->GetY();
        $anchoTexto = $this->GetStringWidth($texto) + 6;
        $anchoLinea = (190 - $anchoTexto) / 2;

        $this->SetDrawColor(190, 190, 190);
        $this->Line(10, $y + 3, 10 + $anchoLinea, $y + 3);
        $this->SetXY(10 + $anchoLinea, $y);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(110, 110, 110);
        $this->Cell($anchoTexto, 6, $texto, 0, 0, 'C');
        $this->Line(10 + $anchoLinea + $anchoTexto, $y + 3, 200, $y + 3);
        $this->Ln(10);
    }

    // Fila "Etiqueta: Valor" (usada en el reporte individual)
    public function filaDatoEstudiante(string $etiqueta, string $valor): void {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(20, 83, 45);
        $this->Cell(45, 7, $this->t($etiqueta . ':'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 7, $this->t($valor), 0, 1);
    }

    private function ajustarTextoMultilinea(string $texto, float $anchoCelda, int $altoMinimo = 7): array {
        $texto = str_replace(["\r\n", "\r"], "\n", $this->t($texto));
        $texto = trim($texto) === '' ? '-' : $texto;
        $anchoPorLinea = max(18, (int)($anchoCelda / 2.2));
        $texto = wordwrap($texto, $anchoPorLinea, "\n");
        $lineas = substr_count($texto, "\n") + 1;
        $altoFila = max($altoMinimo, $lineas * 5);

        return [$texto, $altoFila];
    }

    // Tabla de faltas de un estudiante (reporte individual)
    public function tablaFaltas(array $faltas): void {
        $anchos  = [22, 50, 25, 93];
        $titulos = ['Fecha', 'Tipo de falta', 'Gravedad', 'Descripcion'];

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(243, 243, 243);
        $this->SetTextColor(60, 60, 60);
        $this->SetDrawColor(210, 210, 210);
        foreach ($titulos as $i => $tit) {
            $this->Cell($anchos[$i], 7, $this->t($tit), 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(30, 30, 30);
        if (!$faltas) {
            $this->Cell(array_sum($anchos), 7, $this->t('Sin faltas registradas'), 1, 1, 'C');
            return;
        }
        foreach ($faltas as $f) {
            [$textoDescripcion, $altoFila] = $this->ajustarTextoMultilinea($f['descripcion'] ?? '-', $anchos[3]);
            $startX = $this->GetX();
            $startY = $this->GetY();

            $this->Cell($anchos[0], $altoFila, $this->t($f['fecha']), 1, 0, 'L');
            $this->Cell($anchos[1], $altoFila, $this->t($f['tipo_falta']), 1, 0, 'L');
            $this->Cell($anchos[2], $altoFila, $this->t(ucfirst($f['gravedad'])), 1, 0, 'L');

            // Descripción en MultiCell: colocar en la columna correcta y mantener la misma altura de fila
            $this->SetXY($startX + $anchos[0] + $anchos[1] + $anchos[2], $startY);
            $this->MultiCell($anchos[3], 5, $textoDescripcion, 1, 'L');

            // Mover el cursor al inicio de la siguiente fila
            $this->SetXY($startX, $startY + $altoFila);
        }
    }

    // Tabla de sanciones de un estudiante (reporte individual)
    public function tablaSanciones(array $sanciones): void {
        $anchos  = [35, 40, 30, 85];
        $titulos = ['Tipo de sancion', 'Periodo', 'Estado', 'Descripcion'];

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(243, 243, 243);
        $this->SetTextColor(60, 60, 60);
        $this->SetDrawColor(210, 210, 210);
        foreach ($titulos as $i => $tit) {
            $this->Cell($anchos[$i], 7, $this->t($tit), 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(30, 30, 30);
        if (!$sanciones) {
            $this->Cell(array_sum($anchos), 7, $this->t('Sin sanciones registradas'), 1, 1, 'C');
            return;
        }
        foreach ($sanciones as $s) {
            $periodo = $s['fecha_inicio'] . ($s['fecha_fin'] ? ' - ' . $s['fecha_fin'] : '');
            [$textoDescripcion, $altoFila] = $this->ajustarTextoMultilinea($s['descripcion'] ?? '-', $anchos[3]);
            $startX = $this->GetX();
            $startY = $this->GetY();

            $this->Cell($anchos[0], $altoFila, $this->t($s['tipo_sancion']), 1, 0, 'L');
            $this->Cell($anchos[1], $altoFila, $this->t($periodo), 1, 0, 'L');
            $this->Cell($anchos[2], $altoFila, $this->t(ucfirst($s['estado'])), 1, 0, 'L');

            $this->SetXY($startX + $anchos[0] + $anchos[1] + $anchos[2], $startY);
            $this->MultiCell($anchos[3], 5, $textoDescripcion, 1, 'L');

            $this->SetXY($startX, $startY + $altoFila);
        }
    }

    // Panel superior de datos de la seccion (reporte de grupo)
    public function bloqueInfoSeccion(string $especialidad, string $anio, string $seccion, ?string $fechaInicio, int $totalEstudiantes): void {
        $y0 = $this->GetY();
        $this->SetDrawColor(210, 210, 210);
        $this->Rect(10, $y0, 190, 20);

        $col = 47.5;
        $periodo = $fechaInicio ? ($fechaInicio . ' - ' . date('Y-m-d')) : 'Sin faltas registradas';
        $gradoSeccion = ($anio && $seccion) ? ($anio . 'ro/o "' . $seccion . '"') : '-';

        $datos = [
            ['Especialidad', $especialidad ?: '-'],
            ['Grado y Seccion', $gradoSeccion],
            ['Periodo', $periodo],
            ['Total de estudiantes', (string) $totalEstudiantes],
        ];
        foreach ($datos as $i => $d) {
            $x = 10 + $i * $col;
            $this->SetXY($x + 3, $y0 + 3);
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(20, 83, 45);
            $this->Cell($col - 6, 5, $this->t($d[0] . ':'), 0, 2);
            $this->SetX($x + 3);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(30, 30, 30);
            $this->Cell($col - 6, 6, $this->t($d[1]), 0, 0);
        }
        $this->SetY($y0 + 24);
    }

    // Tabla resumen de todos los estudiantes de la seccion (reporte de grupo, 1 sola pagina)
    public function tablaResumenSeccion(array $filas): void {
        $anchos  = [20, 45, 18, 55, 42, 10]; // sum = 190
        $titulos = ['NIE', 'Nombre', 'Codigo', 'Tipos de falta (principales)', 'Sanciones aplicadas', 'Estado'];

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(20, 83, 45);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(20, 83, 45);
        foreach ($titulos as $i => $tit) {
            $this->Cell($anchos[$i], 8, $this->t($tit), 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('Arial', '', 8);
        $this->SetDrawColor(210, 210, 210);
        $relleno = false;
        foreach ($filas as $f) {
            $this->SetFillColor(244, 248, 245);
            $this->SetTextColor(30, 30, 30);
            $tiposFalta   = $f['tipos']     ? implode(', ', array_slice($f['tipos'], 0, 2))     : '-';
            $tiposSancion = $f['sanciones'] ? implode(', ', array_slice($f['sanciones'], 0, 2)) : '-';

            $nombre = $f['nombre'];
            $codigo = $f['codigo'];
            $estado = $f['activo'] ? 'Activo' : 'Inactivo';

            [$textoTiposFalta, $altoFalta]     = $this->ajustarTextoMultilinea($tiposFalta, $anchos[3], 5);
            [$textoTiposSancion, $altoSancion] = $this->ajustarTextoMultilinea($tiposSancion, $anchos[4], 5);
            $altoFila = max($altoFalta, $altoSancion, 7);

            $startX = $this->GetX();
            $startY = $this->GetY();

            $this->Cell($anchos[0], $altoFila, $this->t($f['nie']), 1, 0, 'C', $relleno);
            $this->Cell($anchos[1], $altoFila, $this->t($nombre), 1, 0, 'L', $relleno);
            $this->Cell($anchos[2], $altoFila, $this->t($codigo), 1, 0, 'C', $relleno);

            // Tipos de falta
            $this->SetXY($startX + $anchos[0] + $anchos[1] + $anchos[2], $startY);
            $this->MultiCell($anchos[3], 5, $textoTiposFalta, 1, 'L');

            // Tipos de sancion
            $this->SetXY($startX + $anchos[0] + $anchos[1] + $anchos[2] + $anchos[3], $startY);
            $this->MultiCell($anchos[4], 5, $textoTiposSancion, 1, 'L');

            // Estado en la ultima columna (alineado verticalmente con la fila)
            $this->SetXY($startX + array_sum($anchos) - $anchos[5], $startY);
            $this->Cell($anchos[5], $altoFila, $this->t($estado), 1, 0, 'C', $relleno);

            // Avanzar al inicio de la siguiente fila
            $this->SetXY($startX, $startY + $altoFila);
            $relleno = !$relleno;
        }
    }

    // Panel inferior de totales (reporte de grupo)
    public function bloqueTotales(int $totalFaltas, float $promedio, int $totalSancionesActivas, int $sinSanciones): void {
        $y0 = $this->GetY() + 4;
        $this->SetDrawColor(210, 210, 210);
        $this->Rect(10, $y0, 190, 18);

        $col = 47.5;
        $datos = [
            ['Total de faltas', (string) $totalFaltas],
            ['Promedio de faltas/estudiante', (string) $promedio],
            ['Total de sanciones activas', (string) $totalSancionesActivas],
            ['Estudiantes sin sanciones', (string) $sinSanciones],
        ];
        foreach ($datos as $i => $d) {
            $x = 10 + $i * $col;
            $this->SetXY($x + 3, $y0 + 3);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Cell($col - 6, 5, $this->t($d[0]), 0, 2);
            $this->SetX($x + 3);
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor(20, 83, 45);
            $this->Cell($col - 6, 8, $this->t($d[1]), 0, 0);
        }
        $this->SetY($y0 + 22);
    }

    // Lineas de firma al final del reporte
    public function dibujarFirmas(): void {
        $y = $this->GetY() + 15;
        if ($y > 265) $y = 265;
        $this->SetY($y);
        $this->SetDrawColor(120, 120, 120);
        $this->SetTextColor(80, 80, 80);
        $this->SetFont('Arial', '', 9);

        $this->Line(20, $y, 80, $y);
        $this->Line(130, $y, 190, $y);
        $this->SetY($y + 2);
        $this->Cell(90, 6, $this->t('Coordinador de disciplina'), 0, 0, 'C');
        $this->Cell(20, 6, '', 0, 0);
        $this->Cell(90, 6, $this->t('Director'), 0, 1, 'C');
    }
}