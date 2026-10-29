<?php
// ============================================================
// EXPORTACION A PDF - REPORTE EJECUTIVO (FPDF)
// Estetica profesional segun normas APA 7:
//   - Margenes de 2.54 cm (1 pulgada) en los cuatro lados
//   - Tipografia Times New Roman 12, interlineado 1.5
//   - Sangria de primera linea de 1.27 cm en parrafos
//   - Figuras numeradas: "Figura N" (negrita) y titulo en
//     cursiva ARRIBA de la imagen; "Nota." con sangria
//     francesa DEBAJO
//   - Numero de pagina en la esquina superior derecha
//   - Figura 1 junto al resumen (pagina 1) y despues
//     maximo 2 figuras por pagina
// ============================================================

namespace App\Export;

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';

class PdfExport extends \FPDF
{
    // Paleta corporativa (portada y acentos)
    private const OLIVO  = [85, 107, 47];
    private const DORADO = [212, 175, 55];
    private const GRIS   = [108, 117, 125];
    private const TEXTO  = [33, 37, 41];

    // Normas APA
    private const MARGEN_APA    = 25.4;  // 2.54 cm = 1 pulgada
    private const SANGRIA_APA   = 12.7;  // primera linea: 0.5 pulgadas
    private const ALTO_LINEA    = 6.2;   // interlineado 1.5 aprox. (12 pt)
    private const ANCHO_FIGURA  = 150;   // ancho maximo de imagen (mm)
    private const ALTO_NOTA     = 5;     // alto de linea de las notas (10 pt)

    /**
     * Genera el reporte ejecutivo.
     * $descargar=true  -> fuerza la descarga en el navegador (controlador)
     * $descargar=false -> devuelve el PDF como string (pruebas automaticas)
     */
    public function generarReporteEjecutivo(
        array $stats,
        string $estado,
        string $periodo,
        string $cliente = '',
        string $qr = '',
        array $chartData = [],
        array $imagens = [],
        bool $descargar = true
    ) {
        // Guardar imagenes como archivos temporales
        $archivos = [];
        foreach ($imagens as $tipo => $base64) {
            if (empty($base64) || !is_string($base64)) continue;
            $data = explode(',', $base64);
            $imagen = isset($data[1]) ? base64_decode($data[1]) : false;
            if ($imagen) {
                $filename = sys_get_temp_dir() . '/chart_' . $tipo . '_' . uniqid('', true) . '.png';
                if (file_put_contents($filename, $imagen) !== false) {
                    $archivos[$tipo] = $filename;
                }
            }
        }

        $this->AddPage();
        $this->portada($estado, $cliente, $qr, $periodo);
        $this->resumenEjecutivo($stats);
        $this->seccionFiguras($archivos);

        $nombre = 'reporte_ejecutivo_' . date('Ymd_His') . '.pdf';
        if ($descargar) {
            $this->Output('D', $nombre);
            exit;
        }
        return $this->Output('S', $nombre);
    }

    // ========================================================
    // NUMERO DE PAGINA (norma APA: esquina superior derecha)
    // Se dibuja automaticamente en cada AddPage().
    // ========================================================
    public function Header(): void
    {
        $this->SetY(self::MARGEN_APA);
        $this->SetX(self::MARGEN_APA);
        $this->SetFont('Times', '', 12);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(0, 6, (string) $this->PageNo(), 0, 0, 'R');
        // El contenido comienza una linea debajo del numero
        $this->SetY(self::MARGEN_APA + 8);
    }

    // ========================================================
    // PORTADA: logo + marca + encabezado enriquecido + filtros
    // ========================================================
    private function portada(string $estado, string $cliente, string $qr, string $periodo): void
    {
        $util = $this->lMargin + $this->fw; // borde derecho del area util

        // Logo vectorial de bicicleta (izquierda, dentro del margen)
        $this->dibujarBicicleta(self::MARGEN_APA + 3, 44);

        // --- Encabezado enriquecido ---
        $this->SetY(self::MARGEN_APA + 8);
        $this->SetX(54);

        $this->SetFont('Times', 'B', 18);
        $this->SetTextColor(...self::OLIVO);
        $this->Cell(0, 9, 'Ebike Manager', 0, 1);

        $this->SetX(54);
        $this->SetFont('Times', '', 11);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(0, 6, 'Reporte Ejecutivo de Alquileres de Bicicletas Electricas', 0, 1);

        $this->SetX(54);
        $this->SetFont('Times', 'I', 9.5);
        $this->SetTextColor(...self::GRIS);
        $this->Cell(0, 5.5, 'Sistema de Gestion de Flota Electrica', 0, 1);

        $this->SetX(54);
        $this->SetFont('Times', '', 9);
        $this->Cell(0, 5, 'Datos en vivo desde MySQL mediante procedimientos almacenados', 0, 1);

        // Doble linea divisoria (olivo gruesa + filete dorado)
        $this->SetDrawColor(...self::OLIVO);
        $this->SetLineWidth(0.8);
        $this->Line(self::MARGEN_APA, 57.5, $util, 57.5);
        $this->SetDrawColor(...self::DORADO);
        $this->SetLineWidth(0.3);
        $this->Line(self::MARGEN_APA, 58.8, $util, 58.8);
        $this->Ln(5);

        // Filtros aplicados y fecha del reporte
        $this->SetFont('Times', '', 10);
        $this->SetTextColor(...self::GRIS);
        $this->Cell(0, 5, 'Filtros aplicados: Estado = ' . ($estado ?: 'TODOS')
            . ' | Cliente = ' . ($cliente ?: 'Todos')
            . ' | QR = ' . ($qr ?: 'Todos')
            . ' | Periodo = ' . ($periodo ?: 'TODOS'), 0, 1);
        $this->Cell(0, 5, 'Fecha del reporte: ' . date('Y-m-d H:i:s'), 0, 1);
        $this->Ln(5);
    }

    /**
     * Logo de bicicleta dibujado vectorialmente (curvas Bezier + lineas).
     * Ocupa aprox. 24 x 13 mm con esquina superior izquierda en ($x, $y).
     */
    private function dibujarBicicleta(float $x, float $y): void
    {
        $r  = 4.6;                     // radio de las ruedas
        $ry = $y + $r;                 // altura del eje de las ruedas
        $xr = $x + $r;                 // centro rueda trasera
        $xf = $x + 18.5;               // centro rueda delantera
        $bb = $x + 11.6;               // eje del pedalier
        $sx = $x + 9.4;  $sy = $y - 1.3;  // tubo del sillin
        $hx = $x + 14.6; $hy = $y - 1.3;  // tubo del manubrio

        // Ruedas
        $this->SetDrawColor(...self::OLIVO);
        $this->SetLineWidth(0.7);
        $this->Circle($xr, $ry, $r, 'D');
        $this->Circle($xf, $ry, $r, 'D');

        // Cuadro (triangulo clasico de bicicleta)
        $this->SetLineWidth(0.9);
        $this->Line($xr, $ry, $bb, $ry);   // vaina inferior trasera
        $this->Line($bb, $ry, $sx, $sy);   // tubo del sillin
        $this->Line($xr, $ry, $sx, $sy);   // tirante trasero
        $this->Line($sx, $sy, $hx, $hy);   // tubo superior
        $this->Line($bb, $ry, $hx, $hy);   // tubo diagonal
        $this->Line($hx, $hy, $xf, $ry);   // horquilla delantera

        // Sillin y manubrio (acento dorado)
        $this->SetDrawColor(...self::DORADO);
        $this->SetLineWidth(1.2);
        $this->Line($sx - 2.0, $sy, $sx + 2.3, $sy);
        $this->Line($hx - 0.4, $hy - 1.3, $hx + 2.1, $hy + 0.5);

        // Pedalier (punto solido dorado)
        $this->SetFillColor(...self::DORADO);
        $this->Circle($bb, $ry, 0.8, 'F');
    }

    // ========================================================
    // RESUMEN EJECUTIVO (titulo nivel 1 APA: centrado y negrita;
    // parrafos con sangria de primera linea e interlineado 1.5)
    // ========================================================
    private function resumenEjecutivo(array $stats): void
    {
        $this->SetFont('Times', 'B', 13);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(0, 10, 'Resumen Ejecutivo', 0, 1, 'C');
        $this->Ln(1);

        // --- Cifras reales calculadas desde los procedimientos almacenados ---
        $totalAlq    = (int)($stats['total_alquileres'] ?? 0);
        $ingresos    = (float)($stats['ingresos_totales'] ?? 0);
        $usuarios    = (int)($stats['total_usuarios'] ?? 0);
        $bicis       = (int)($stats['total_bicicletas'] ?? 0);
        $disponibles = (int)($stats['bicicletas_disponibles'] ?? 0);
        $activos     = (int)($stats['alquileres_activos'] ?? 0);

        $ingresoMedio = $totalAlq > 0 ? $ingresos / $totalAlq : 0.0;
        $ocupacion    = $bicis > 0 ? $activos / $bicis * 100 : 0.0;
        $pctDisp      = $bicis > 0 ? $disponibles / $bicis * 100 : 0.0;

        $this->SetFont('Times', '', 12);

        $p1 = 'Durante el periodo analizado la plataforma registro '
            . number_format($totalAlq, 0, ',', '.') . ' alquileres, con ingresos totales de '
            . number_format($ingresos, 2, ',', '.') . ' EUR y un ingreso medio por alquiler de '
            . number_format($ingresoMedio, 2, ',', '.') . ' EUR. '
            . 'La flota esta compuesta por ' . number_format($bicis) . ' bicicletas, de las cuales '
            . number_format($disponibles) . ' se encuentran disponibles ('
            . number_format($pctDisp, 1, ',', '.') . '%) y ' . number_format($activos)
            . ' estan en alquiler activo, lo que supone una ocupacion del '
            . number_format($ocupacion, 1, ',', '.') . '% de la flota. '
            . 'La base de clientes suma ' . number_format($usuarios) . ' usuarios registrados.';
        $this->parrafoApa($p1);
        $this->Ln(2);

        $p2 = 'El servicio se analiza a traves de cinco figuras: '
            . '(1) ingresos por estacion; (2) histograma de duraciones; '
            . '(3) evolucion mensual de alquileres; (4) composicion por estado; '
            . 'y (5) indicadores del sistema, todos con sus valores numericos.';
        $this->parrafoApa($p2);
        $this->Ln(4);
    }

    /**
     * Parrafo con formato APA y texto JUSTIFICADO (homogeneo):
     * sangria de 1.27 cm en la primera linea, todas las lineas
     * alineadas a ambos margenes excepto la ultima.
     */
    private function parrafoApa(string $texto): void
    {
        $palabras = preg_split('/\s+/', trim($texto));
        $lineas   = [];
        $linea    = '';
        $limite   = $this->fw - self::SANGRIA_APA;   // 1a linea: mas angosta por la sangria

        foreach ($palabras as $palabra) {
            if ($palabra === '') continue;
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra);
            if ($this->GetStringWidth($prueba) <= $limite) {
                $linea = $prueba;
            } else {
                if ($linea !== '') $lineas[] = $linea;
                $linea  = $palabra;
                $limite = $this->fw;                 // resto de lineas: ancho completo
            }
        }
        if ($linea !== '') $lineas[] = $linea;

        $total = count($lineas);
        foreach ($lineas as $i => $l) {
            $anchoLinea = ($i === 0) ? $this->fw - self::SANGRIA_APA : $this->fw;
            $this->SetX($i === 0 ? $this->lMargin + self::SANGRIA_APA : $this->lMargin);
            if ($i === $total - 1) {
                // Ultima linea: alineada a la izquierda (norma tipografica)
                $this->Cell($this->fw, self::ALTO_LINEA, $l, 0, 1, 'L');
            } else {
                $this->lineaJustificada($l, $anchoLinea);
            }
        }
    }

    /**
     * Dibuja una linea de texto JUSTIFICADA hasta el ancho indicado
     * usando el operador de espaciado entre palabras (Tw) del PDF:
     * el espacio extra se reparte uniformemente entre los espacios,
     * de modo que el texto llega exactamente al margen derecho.
     */
    private function lineaJustificada(string $texto, float $anchoObjetivo): void
    {
        $numEspacios = substr_count($texto, ' ');

        // Linea de una sola palabra: no hay nada que repartir
        if ($numEspacios < 1) {
            $this->Cell($this->fw, self::ALTO_LINEA, $texto, 0, 1, 'L');
            return;
        }

        $anchoTexto = $this->GetStringWidth($texto);
        $extraMm    = max(0, $anchoObjetivo - $anchoTexto);
        $twPt       = ($extraMm / $numEspacios) * $this->k;

        // Emision manual (misma formula de posicion que Cell, mas el Tw)
        $x = $this->x;
        $y = $this->y;
        $h = self::ALTO_LINEA;
        $baseY = ($this->h - $y - 0.5 * $h + 0.3 * $this->FontSize) * $this->k;

        $s  = sprintf('BT %.2f %.2f Td %.3F Tw ', $x * $this->k, $baseY, $twPt);
        $s .= '(' . $this->_escape($texto) . ') Tj ET';
        if ($this->ColorFlag) {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }
        $this->_out($s);
        $this->_out('0 Tw');    // restaurar el espaciado normal

        $this->y += $h;
        $this->x = $this->lMargin;
    }

    // ========================================================
    // FIGURAS (norma APA 7):
    //   "Figura N" en negrita y titulo en cursiva ARRIBA,
    //   imagen centrada dentro de los margenes y
    //   "Nota." con sangria francesa DEBAJO.
    // Figura 1 junto al resumen en la primera pagina
    // (ancho adaptativo) y despues maximo 2 por pagina.
    // ========================================================
    private function seccionFiguras(array $archivos): void
    {
        $this->SetFont('Times', 'B', 13);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(0, 10, 'Graficas del Periodo', 0, 1, 'C');
        $this->Ln(1);

        $figuras = [
            'barras' => [
                'titulo' => 'Ingresos por Estacion (EUR)',
                'nota'   => 'Compara los ingresos totales generados por cada estacion; la estacion con mayor volumen se destaca en dorado. Elaborado con datos del SP sp_alquiler_buscar_paginado.',
            ],
            'histograma' => [
                'titulo' => 'Duracion de los Alquileres (minutos)',
                'nota'   => 'Distribucion de la cantidad de alquileres por rango de duracion; la barra mas alta indica el patron de uso tipico. Elaborado con datos del SP sp_histograma_duraciones.',
            ],
            'poligono' => [
                'titulo' => 'Evolucion Mensual de Alquileres',
                'nota'   => 'Tendencia del numero de alquileres mes a mes, util para detectar estacionalidad y planificar la disponibilidad de la flota.',
            ],
            'sectores' => [
                'titulo' => 'Composicion de los Alquileres por Estado',
                'nota'   => 'Proporcion de alquileres por estado; cada segmento representa su participacion porcentual sobre el total.',
            ],
            'radar' => [
                'titulo' => 'Indicadores del Sistema (%)',
                'nota'   => 'Salud general de la flota segun el SP sp_indicadores_sistema: bateria promedio, disponibilidad, ocupacion, pagos exitosos y rotacion de uso. Un eje por debajo del 40% se resalta en rojo.',
            ],
        ];

        $n = 0;
        $figurasEnPagina = 0;   // maximo 2 figuras por pagina (norma de estilo)
        foreach ($figuras as $tipo => $def) {
            if (!isset($archivos[$tipo])) continue;
            $imagenPath = $archivos[$tipo];
            $n++;

            // Proporcion real de la imagen (alto/ancho)
            $info    = getimagesize($imagenPath);
            $aspecto = ($info && $info[0] > 0) ? $info[1] / $info[0] : 0.25;

            // Alto del texto del bloque: rotulo (6) + titulo (6) + margen (1)
            // + nota (lineas estimadas) + separaciones (2 + 6)
            $lineasNota = $this->estimarLineas($def['nota'], $this->fw - 18, '', 10);
            $altoTexto  = 6 + 6 + 1 + 2 + ($lineasNota * self::ALTO_NOTA) + 6;

            if ($n === 1) {
                // --- Figura 1: JUNTO al resumen en la primera pagina.
                // El ancho se adapta al espacio que queda disponible.
                $disponible = ($this->h - self::MARGEN_APA) - $this->GetY() - $altoTexto;
                $ancho = self::ANCHO_FIGURA;
                if ($disponible < $ancho * $aspecto) {
                    $ancho = (int)($disponible / $aspecto);
                }
                if ($ancho < 100 || $this->GetY() + $altoTexto + $ancho * $aspecto > $this->h - self::MARGEN_APA) {
                    // Defensa extrema: si ni cabe reducida, pasar a pagina nueva
                    $this->AddPage();
                    $figurasEnPagina = 0;
                    $ancho = self::ANCHO_FIGURA;
                }
            } else {
                // --- Resto de figuras: maximo 2 por pagina ---
                $ancho = self::ANCHO_FIGURA;
                if ($figurasEnPagina >= 2
                    || $this->GetY() + $altoTexto + $ancho * $aspecto > $this->h - self::MARGEN_APA) {
                    $this->AddPage();
                    $figurasEnPagina = 0;
                }
            }

            // --- "Figura N" (negrita) ---
            $this->SetFont('Times', 'B', 12);
            $this->SetTextColor(...self::TEXTO);
            $this->Cell(0, 6, 'Figura ' . $n, 0, 1);

            // --- Titulo de la figura (cursiva) ---
            $this->SetFont('Times', 'I', 12);
            $this->Cell(0, 6, $def['titulo'], 0, 1);

            // --- Imagen centrada dentro del area util ---
            $y = $this->GetY() + 1;
            $x = $this->lMargin + ($this->fw - $ancho) / 2;
            $size = $this->Image($imagenPath, $x, $y, $ancho, 0, 'PNG');
            if ($size) {
                $this->SetY($y + $size['h']);
            }
            $this->Ln(2);

            // --- Nota con sangria francesa ---
            $this->notaApa($def['nota']);
            $this->Ln(6);

            $figurasEnPagina++;

            @unlink($imagenPath);
        }
    }

    /**
     * Cuenta cuantas lineas ocupara un texto con la tipografia indicada.
     * Sirve para reservar espacio antes de dibujar y evitar desbordes.
     */
    private function estimarLineas(string $texto, float $ancho, string $estilo, float $pt): int
    {
        $this->SetFont('Times', $estilo, $pt);
        $linea = '';
        $n = 1;
        foreach (preg_split('/\s+/', trim($texto)) as $palabra) {
            if ($palabra === '') continue;
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra);
            if ($this->GetStringWidth($prueba) <= $ancho) {
                $linea = $prueba;
            } else {
                $n++;
                $linea = $palabra;
            }
        }
        return $n;
    }

    /**
     * Nota de figura segun APA: "Nota." en cursiva seguida del texto
     * en redonda, con sangria francesa y texto JUSTIFICADO (todas las
     * lineas al mismo ancho, excepto la ultima).
     */
    private function notaApa(string $texto): void
    {
        $prefijo   = 'Nota. ';
        $wPrefijo  = $this->GetStringWidth($prefijo) + 1;
        $anchoUtil = $this->fw - $wPrefijo;   // todas las lineas respetan la sangria francesa

        $palabras = preg_split('/\s+/', trim($texto));
        $lineas   = [];
        $linea    = '';
        $limite   = $anchoUtil;

        foreach ($palabras as $palabra) {
            if ($palabra === '') continue;
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra);
            if ($this->GetStringWidth($prueba) <= $limite) {
                $linea = $prueba;
            } else {
                if ($linea !== '') $lineas[] = $linea;
                $linea  = $palabra;
                $limite = $anchoUtil;
            }
        }
        if ($linea !== '') $lineas[] = $linea;

        $total = count($lineas);
        foreach ($lineas as $i => $l) {
            $this->SetX($this->lMargin);
            if ($i === 0) {
                $this->SetFont('Times', 'I', 10);
                $this->Cell($wPrefijo, self::ALTO_NOTA, $prefijo, 0, 0, 'L');
                $this->SetFont('Times', '', 10);
            } else {
                $this->SetX($this->lMargin + $wPrefijo);  // sangria francesa
            }

            if ($i === $total - 1) {
                // Ultima linea: izquierda normal
                $this->Cell($this->fw, self::ALTO_NOTA, $l, 0, 1, 'L');
            } else {
                $this->lineaJustificadaNota($l, $anchoUtil);
            }
        }
    }

    /**
     * Linea justificada de una nota (10 pt, alto de linea menor).
     * Misma tecnica Tw que lineaJustificada pero con ALTO_NOTA.
     */
    private function lineaJustificadaNota(string $texto, float $anchoObjetivo): void
    {
        $numEspacios = substr_count($texto, ' ');

        if ($numEspacios < 1) {
            $this->Cell($this->fw, self::ALTO_NOTA, $texto, 0, 1, 'L');
            return;
        }

        $anchoTexto = $this->GetStringWidth($texto);
        $extraMm    = max(0, $anchoObjetivo - $anchoTexto);
        $twPt       = ($extraMm / $numEspacios) * $this->k;

        $x = $this->x;
        $y = $this->y;
        $h = self::ALTO_NOTA;
        $baseY = ($this->h - $y - 0.5 * $h + 0.3 * $this->FontSize) * $this->k;

        $s  = sprintf('BT %.2f %.2f Td %.3F Tw ', $x * $this->k, $baseY, $twPt);
        $s .= '(' . $this->_escape($texto) . ') Tj ET';
        if ($this->ColorFlag) {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }
        $this->_out($s);
        $this->_out('0 Tw');

        $this->y += $h;
        $this->x = $this->lMargin;
    }
}
