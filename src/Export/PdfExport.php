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

namespace App\Export; // Espacio de nombres de la aplicacion para el modulo de exportacion

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php'; // Carga la libreria FPDF desde vendor

class PdfExport extends \FPDF // Clase que hereda de FPDF para generar PDFs personalizados
{
    // Paleta corporativa (portada y acentos)
    private const OLIVO  = [85, 107, 47];   // Color olivo RGB para titulos y acentos
    private const DORADO = [212, 175, 55];  // Color dorado RGB para detalles y resaltes
    private const GRIS   = [108, 117, 125]; // Color gris RGB para texto secundario
    private const TEXTO  = [33, 37, 41];    // Color de texto principal (gris oscuro)

    // Normas APA
    private const MARGEN_APA    = 25.4;  // Margen APA de 2.54 cm en todos los lados (mm)
    private const SANGRIA_APA   = 12.7;  // Sangria de primera linea de 0.5 pulgadas (mm)
    private const ALTO_LINEA    = 6.2;   // Alto de linea para interlineado 1.5 con fuente 12 pt
    private const ANCHO_FIGURA  = 150;   // Ancho maximo permitido para una imagen (mm)
    private const ALTO_NOTA     = 5;     // Alto de linea para las notas de figura (10 pt)

    /**
     * Genera el reporte ejecutivo.
     * $descargar=true  -> fuerza la descarga en el navegador (controlador)
     * $descargar=false -> devuelve el PDF como string (pruebas automaticas)
     */
    public function generarReporteEjecutivo(
        array $stats,            // Estadisticas calculadas desde los procedimientos almacenados
        string $estado,          // Filtro de estado aplicado al reporte
        string $periodo,         // Periodo temporal filtrado
        string $cliente = '',     // Filtro de cliente (opcional)
        string $qr = '',          // Filtro de codigo QR (opcional)
        array $chartData = [],    // Datos de graficos (no usados directamente aqui)
        array $imagens = [],      // Imagenes en base64 keyed por tipo de grafico
        bool $descargar = true    // Indica si se fuerza descarga o se devuelve string
    ) {
        // Guardar imagenes como archivos temporales
        $archivos = []; // Mapa de tipo => ruta temporal del PNG guardado
        foreach ($imagens as $tipo => $base64) { // Recorre cada imagen base64 recibida
            if (empty($base64) || !is_string($base64)) continue; // Salta entradas vacias o no string
            $data = explode(',', $base64); // Separa el encabezado data:... de los datos base64
            $imagen = isset($data[1]) ? base64_decode($data[1]) : false; // Decodifica el payload base64
            if ($imagen) { // Si la decodificacion fue exitosa
                $filename = sys_get_temp_dir() . '/chart_' . $tipo . '_' . uniqid('', true) . '.png'; // Ruta temporal unica
                if (file_put_contents($filename, $imagen) !== false) { // Guarda el PNG en disco
                    $archivos[$tipo] = $filename; // Registra la ruta para uso posterior
                }
            }
        }

        $this->AddPage(); // Crea la primera pagina del PDF
        $this->portada($estado, $cliente, $qr, $periodo); // Dibuja la portada con filtros
        $this->resumenEjecutivo($stats); // Dibuja el resumen ejecutivo con cifras
        $this->seccionFiguras($archivos); // Dibuja las figuras/graficos

        $nombre = 'reporte_ejecutivo_' . date('Ymd_His') . '.pdf'; // Nombre del archivo con timestamp
        if ($descargar) { // Si se debe forzar descarga
            $this->Output('D', $nombre); // Envia el PDF al navegador como descarga
            exit; // Termina la ejecucion tras la descarga
        }
        return $this->Output('S', $nombre); // Devuelve el PDF como string (para pruebas)
    }

    // ========================================================
    // NUMERO DE PAGINA (norma APA: esquina superior derecha)
    // Se dibuja automaticamente en cada AddPage().
    // ========================================================
    public function Header(): void // Cabecera automatica de FPDF en cada pagina
    {
        $this->SetY(self::MARGEN_APA); // Posiciona el cursor en el margen superior
        $this->SetX(self::MARGEN_APA); // Posiciona el cursor en el margen izquierdo
        $this->SetFont('Times', '', 12); // Fuente Times 12 para el numero de pagina
        $this->SetTextColor(...self::TEXTO); // Color de texto principal
        $this->Cell(0, 6, (string) $this->PageNo(), 0, 0, 'R'); // Imprime el numero de pagina a la derecha
        // El contenido comienza una linea debajo del numero
        $this->SetY(self::MARGEN_APA + 8); // Baja el cursor para iniciar el contenido
    }

    // ========================================================
    // PORTADA: logo + marca + encabezado enriquecido + filtros
    // ========================================================
    private function portada(string $estado, string $cliente, string $qr, string $periodo): void // Dibuja la portada del reporte
    {
        $util = $this->lMargin + $this->fw; // Calcula el borde derecho del area util

        // Logo vectorial de bicicleta (izquierda, dentro del margen)
        $this->dibujarBicicleta(self::MARGEN_APA + 3, 44); // Dibuja el logo vectorial de bicicleta

        // --- Encabezado enriquecido ---
        $this->SetY(self::MARGEN_APA + 8); // Posicion inicial del encabezado
        $this->SetX(54); // Desplaza el texto a la derecha del logo

        $this->SetFont('Times', 'B', 18); // Fuente negrita 18 para el titulo principal
        $this->SetTextColor(...self::OLIVO); // Color olivo para el titulo
        $this->Cell(0, 9, 'Ebike Manager', 0, 1); // Imprime el nombre de la marca

        $this->SetX(54); // Reestablece X para la siguiente linea
        $this->SetFont('Times', '', 11); // Fuente normal 11
        $this->SetTextColor(...self::TEXTO); // Color de texto principal
        $this->Cell(0, 6, 'Reporte Ejecutivo de Alquileres de Bicicletas Electricas', 0, 1); // Subtitulo del reporte

        $this->SetX(54); // Reestablece X
        $this->SetFont('Times', 'I', 9.5); // Fuente cursiva 9.5
        $this->SetTextColor(...self::GRIS); // Color gris
        $this->Cell(0, 5.5, 'Sistema de Gestion de Flota Electrica', 0, 1); // Linea descriptiva

        $this->SetX(54); // Reestablece X
        $this->SetFont('Times', '', 9); // Fuente normal 9
        $this->Cell(0, 5, 'Datos en vivo desde MySQL mediante procedimientos almacenados', 0, 1); // Origen de los datos

        // Doble linea divisoria (olivo gruesa + filete dorado)
        $this->SetDrawColor(...self::OLIVO); // Color de trazo olivo
        $this->SetLineWidth(0.8); // Grosor grueso para la primera linea
        $this->Line(self::MARGEN_APA, 57.5, $util, 57.5); // Dibuja linea olivo
        $this->SetDrawColor(...self::DORADO); // Color de trazo dorado
        $this->SetLineWidth(0.3); // Grosor fino para el filete
        $this->Line(self::MARGEN_APA, 58.8, $util, 58.8); // Dibuja filete dorado
        $this->Ln(5); // Espacio vertical tras las lineas

        // Filtros aplicados y fecha del reporte
        $this->SetFont('Times', '', 10); // Fuente normal 10
        $this->SetTextColor(...self::GRIS); // Color gris para los filtros
        $this->Cell(0, 5, 'Filtros aplicados: Estado = ' . ($estado ?: 'TODOS') // Imprime filtros aplicados
            . ' | Cliente = ' . ($cliente ?: 'Todos')
            . ' | QR = ' . ($qr ?: 'Todos')
            . ' | Periodo = ' . ($periodo ?: 'TODOS'), 0, 1);
        $this->Cell(0, 5, 'Fecha del reporte: ' . date('Y-m-d H:i:s'), 0, 1); // Fecha y hora del reporte
        $this->Ln(5); // Espacio vertical final de portada
    }

    /**
     * Logo de bicicleta dibujado vectorialmente (curvas Bezier + lineas).
     * Ocupa aprox. 24 x 13 mm con esquina superior izquierda en ($x, $y).
     */
    private function dibujarBicicleta(float $x, float $y): void // Dibuja el logo vectorial de bicicleta
    {
        $r  = 4.6;                     // Radio de las ruedas (mm)
        $ry = $y + $r;                 // Altura del eje de las ruedas
        $xr = $x + $r;                 // Centro de la rueda trasera
        $xf = $x + 18.5;               // Centro de la rueda delantera
        $bb = $x + 11.6;               // Eje del pedalier
        $sx = $x + 9.4;  $sy = $y - 1.3;  // Tubo del sillin
        $hx = $x + 14.6; $hy = $y - 1.3;  // Tubo del manubrio

        // Ruedas
        $this->SetDrawColor(...self::OLIVO); // Color olivo para las ruedas
        $this->SetLineWidth(0.7); // Grosor de linea de las ruedas
        $this->Circle($xr, $ry, $r, 'D'); // Dibuja rueda trasera (contorno)
        $this->Circle($xf, $ry, $r, 'D'); // Dibuja rueda delantera (contorno)

        // Cuadro (triangulo clasico de bicicleta)
        $this->SetLineWidth(0.9); // Grosor del cuadro
        $this->Line($xr, $ry, $bb, $ry);   // Vaina inferior trasera
        $this->Line($bb, $ry, $sx, $sy);   // Tubo del sillin
        $this->Line($xr, $ry, $sx, $sy);   // Tirante trasero
        $this->Line($sx, $sy, $hx, $hy);   // Tubo superior
        $this->Line($bb, $ry, $hx, $hy);   // Tubo diagonal
        $this->Line($hx, $hy, $xf, $ry);   // Horquilla delantera

        // Sillin y manubrio (acento dorado)
        $this->SetDrawColor(...self::DORADO); // Color dorado para sillin y manubrio
        $this->SetLineWidth(1.2); // Grosor mas grueso
        $this->Line($sx - 2.0, $sy, $sx + 2.3, $sy); // Dibuja el sillin
        $this->Line($hx - 0.4, $hy - 1.3, $hx + 2.1, $hy + 0.5); // Dibuja el manubrio

        // Pedalier (punto solido dorado)
        $this->SetFillColor(...self::DORADO); // Color de relleno dorado
        $this->Circle($bb, $ry, 0.8, 'F'); // Dibuja pedalier relleno
    }

    // ========================================================
    // RESUMEN EJECUTIVO (titulo nivel 1 APA: centrado y negrita;
    // parrafos con sangria de primera linea e interlineado 1.5)
    // ========================================================
    private function resumenEjecutivo(array $stats): void // Dibuja el resumen ejecutivo
    {
        $this->SetFont('Times', 'B', 13); // Fuente negrita 13 para el titulo
        $this->SetTextColor(...self::TEXTO); // Color de texto principal
        $this->Cell(0, 10, 'Resumen Ejecutivo', 0, 1, 'C'); // Titulo centrado
        $this->Ln(1); // Espacio tras el titulo

        // --- Cifras reales calculadas desde los procedimientos almacenados ---
        $totalAlq    = (int)($stats['total_alquileres'] ?? 0); // Total de alquileres
        $ingresos    = (float)($stats['ingresos_totales'] ?? 0); // Ingresos totales
        $usuarios    = (int)($stats['total_usuarios'] ?? 0); // Total de usuarios
        $bicis       = (int)($stats['total_bicicletas'] ?? 0); // Total de bicicletas
        $disponibles = (int)($stats['bicicletas_disponibles'] ?? 0); // Bicicletas disponibles
        $activos     = (int)($stats['alquileres_activos'] ?? 0); // Alquileres activos

        $ingresoMedio = $totalAlq > 0 ? $ingresos / $totalAlq : 0.0; // Ingreso medio por alquiler
        $ocupacion    = $bicis > 0 ? $activos / $bicis * 100 : 0.0; // Porcentaje de ocupacion de flota
        $pctDisp      = $bicis > 0 ? $disponibles / $bicis * 100 : 0.0; // Porcentaje de disponibilidad

        $this->SetFont('Times', '', 12); // Fuente normal 12 para parrafos

        $p1 = 'Durante el periodo analizado la plataforma registro ' // Construye primer parrafo
            . number_format($totalAlq, 0, ',', '.') . ' alquileres, con ingresos totales de '
            . number_format($ingresos, 2, ',', '.') . ' EUR y un ingreso medio por alquiler de '
            . number_format($ingresoMedio, 2, ',', '.') . ' EUR. '
            . 'La flota esta compuesta por ' . number_format($bicis) . ' bicicletas, de las cuales '
            . number_format($disponibles) . ' se encuentran disponibles ('
            . number_format($pctDisp, 1, ',', '.') . '%) y ' . number_format($activos)
            . ' estan en alquiler activo, lo que supone una ocupacion del '
            . number_format($ocupacion, 1, ',', '.') . '% de la flota. '
            . 'La base de clientes suma ' . number_format($usuarios) . ' usuarios registrados.';
        $this->parrafoApa($p1); // Dibuja el primer parrafo con formato APA
        $this->Ln(2); // Espacio tras el parrafo

        $p2 = 'El servicio se analiza a traves de cinco figuras: ' // Construye segundo parrafo
            . '(1) ingresos por estacion; (2) histograma de duraciones; '
            . '(3) evolucion mensual de alquileres; (4) composicion por estado; '
            . 'y (5) indicadores del sistema, todos con sus valores numericos.';
        $this->parrafoApa($p2); // Dibuja el segundo parrafo
        $this->Ln(4); // Espacio final del resumen
    }

    /**
     * Parrafo con formato APA y texto JUSTIFICADO (homogeneo):
     * sangria de 1.27 cm en la primera linea, todas las lineas
     * alineadas a ambos margenes excepto la ultima.
     */
    private function parrafoApa(string $texto): void // Dibuja un parrafo APA justificado
    {
        $palabras = preg_split('/\s+/', trim($texto)); // Divide el texto en palabras
        $lineas   = []; // Acumula las lineas resultantes
        $linea    = ''; // Linea en construccion
        $limite   = $this->fw - self::SANGRIA_APA;   // 1a linea: mas angosta por la sangria

        foreach ($palabras as $palabra) { // Recorre cada palabra para armar lineas
            if ($palabra === '') continue; // Salta palabras vacias
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra); // Prueba agregar palabra
            if ($this->GetStringWidth($prueba) <= $limite) { // Si cabe en el ancho disponible
                $linea = $prueba; // Agrega la palabra a la linea
            } else { // Si no cabe
                if ($linea !== '') $lineas[] = $linea; // Guarda la linea completa
                $linea  = $palabra; // Inicia nueva linea con la palabra
                $limite = $this->fw;                 // resto de lineas: ancho completo
            }
        }
        if ($linea !== '') $lineas[] = $linea; // Guarda la ultima linea pendiente

        $total = count($lineas); // Total de lineas del parrafo
        foreach ($lineas as $i => $l) { // Dibuja cada linea
            $anchoLinea = ($i === 0) ? $this->fw - self::SANGRIA_APA : $this->fw; // Ancho segun linea
            $this->SetX($i === 0 ? $this->lMargin + self::SANGRIA_APA : $this->lMargin); // Sangria en primera linea
            if ($i === $total - 1) { // Ultima linea
                // Ultima linea: alineada a la izquierda (norma tipografica)
                $this->Cell($this->fw, self::ALTO_LINEA, $l, 0, 1, 'L'); // Imprime sin justificar
            } else { // Lineas intermedias
                $this->lineaJustificada($l, $anchoLinea); // Justifica la linea
            }
        }
    }

    /**
     * Dibuja una linea de texto JUSTIFICADA hasta el ancho indicado
     * usando el operador de espaciado entre palabras (Tw) del PDF:
     * el espacio extra se reparte uniformemente entre los espacios,
     * de modo que el texto llega exactamente al margen derecho.
     */
    private function lineaJustificada(string $texto, float $anchoObjetivo): void // Justifica una linea de texto
    {
        $numEspacios = substr_count($texto, ' '); // Cuenta los espacios de la linea

        // Linea de una sola palabra: no hay nada que repartir
        if ($numEspacios < 1) { // Si no hay espacios
            $this->Cell($this->fw, self::ALTO_LINEA, $texto, 0, 1, 'L'); // Imprime sin justificar
            return; // Sale de la funcion
        }

        $anchoTexto = $this->GetStringWidth($texto); // Ancho real del texto
        $extraMm    = max(0, $anchoObjetivo - $anchoTexto); // Espacio extra a distribuir
        $twPt       = ($extraMm / $numEspacios) * $this->k; // Espaciado Tw en puntos

        // Emision manual (misma formula de posicion que Cell, mas el Tw)
        $x = $this->x; // Posicion X actual
        $y = $this->y; // Posicion Y actual
        $h = self::ALTO_LINEA; // Alto de linea
        $baseY = ($this->h - $y - 0.5 * $h + 0.3 * $this->FontSize) * $this->k; // Y base en puntos

        $s  = sprintf('BT %.2f %.2f Td %.3F Tw ', $x * $this->k, $baseY, $twPt); // Operadores PDF BT/Td/Tw
        $s .= '(' . $this->_escape($texto) . ') Tj ET'; // Texto y fin de texto
        if ($this->ColorFlag) { // Si hay color de texto activo
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q'; // Envuelve con q/Q para color
        }
        $this->_out($s); // Emite el operador al PDF
        $this->_out('0 Tw');    // restaurar el espaciado normal

        $this->y += $h; // Avanza la posicion Y
        $this->x = $this->lMargin; // Reinicia X al margen izquierdo
    }

    // ========================================================
    // FIGURAS (norma APA 7):
    //   "Figura N" en negrita y titulo en cursiva ARRIBA,
    //   imagen centrada dentro de los margenes y
    //   "Nota." con sangria francesa DEBAJO.
    // Figura 1 junto al resumen en la primera pagina
    // (ancho adaptativo) y despues maximo 2 por pagina.
    // ========================================================
    private function seccionFiguras(array $archivos): void // Dibuja la seccion de figuras
    {
        $this->SetFont('Times', 'B', 13); // Fuente negrita 13 para el titulo
        $this->SetTextColor(...self::TEXTO); // Color de texto principal
        $this->Cell(0, 10, 'Graficas del Periodo', 0, 1, 'C'); // Titulo centrado de la seccion
        $this->Ln(1); // Espacio tras el titulo

        $figuras = [ // Definicion de las 5 figuras del reporte
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

        $n = 0; // Contador de figuras dibujadas
        $figurasEnPagina = 0;   // maximo 2 figuras por pagina (norma de estilo)
        foreach ($figuras as $tipo => $def) { // Recorre las figuras definidas
            if (!isset($archivos[$tipo])) continue; // Salta si no hay imagen para ese tipo
            $imagenPath = $archivos[$tipo]; // Ruta del PNG temporal
            $n++; // Incrementa el numero de figura

            // Proporcion real de la imagen (alto/ancho)
            $info    = getimagesize($imagenPath); // Obtiene dimensiones de la imagen
            $aspecto = ($info && $info[0] > 0) ? $info[1] / $info[0] : 0.25; // Calcula ratio alto/ancho

            // Alto del texto del bloque: rotulo (6) + titulo (6) + margen (1)
            // + nota (lineas estimadas) + separaciones (2 + 6)
            $lineasNota = $this->estimarLineas($def['nota'], $this->fw - 18, '', 10); // Estima lineas de la nota
            $altoTexto  = 6 + 6 + 1 + 2 + ($lineasNota * self::ALTO_NOTA) + 6; // Alto total del bloque de texto

            if ($n === 1) { // Primera figura: junto al resumen
                // --- Figura 1: JUNTO al resumen en la primera pagina.
                // El ancho se adapta al espacio que queda disponible.
                $disponible = ($this->h - self::MARGEN_APA) - $this->GetY() - $altoTexto; // Espacio vertical disponible
                $ancho = self::ANCHO_FIGURA; // Ancho inicial maximo
                if ($disponible < $ancho * $aspecto) { // Si no cabe con el ancho maximo
                    $ancho = (int)($disponible / $aspecto); // Reduce el ancho proporcionalmente
                }
                if ($ancho < 100 || $this->GetY() + $altoTexto + $ancho * $aspecto > $this->h - self::MARGEN_APA) {
                    // Defensa extrema: si ni cabe reducida, pasar a pagina nueva
                    $this->AddPage(); // Nueva pagina
                    $figurasEnPagina = 0; // Reinicia contador de pagina
                    $ancho = self::ANCHO_FIGURA; // Restaura ancho maximo
                }
            } else { // Resto de figuras
                // --- Resto de figuras: maximo 2 por pagina ---
                $ancho = self::ANCHO_FIGURA; // Ancho maximo
                if ($figurasEnPagina >= 2 // Si ya hay 2 figuras en la pagina
                    || $this->GetY() + $altoTexto + $ancho * $aspecto > $this->h - self::MARGEN_APA) { // O no cabe
                    $this->AddPage(); // Nueva pagina
                    $figurasEnPagina = 0; // Reinicia contador
                }
            }

            // --- "Figura N" (negrita) ---
            $this->SetFont('Times', 'B', 12); // Fuente negrita 12
            $this->SetTextColor(...self::TEXTO); // Color de texto principal
            $this->Cell(0, 6, 'Figura ' . $n, 0, 1); // Imprime "Figura N"

            // --- Titulo de la figura (cursiva) ---
            $this->SetFont('Times', 'I', 12); // Fuente cursiva 12
            $this->Cell(0, 6, $def['titulo'], 0, 1); // Imprime el titulo de la figura

            // --- Imagen centrada dentro del area util ---
            $y = $this->GetY() + 1; // Y con margen superior de 1 mm
            $x = $this->lMargin + ($this->fw - $ancho) / 2; // X centrado horizontalmente
            $size = $this->Image($imagenPath, $x, $y, $ancho, 0, 'PNG'); // Inserta la imagen PNG
            if ($size) { // Si Image devuelve dimensiones
                $this->SetY($y + $size['h']); // Actualiza Y tras la imagen
            }
            $this->Ln(2); // Espacio tras la imagen

            // --- Nota con sangria francesa ---
            $this->notaApa($def['nota']); // Dibuja la nota APA
            $this->Ln(6); // Espacio tras la nota

            $figurasEnPagina++; // Incrementa figuras en la pagina actual

            @unlink($imagenPath); // Elimina el archivo temporal de la imagen
        }
    }

    /**
     * Cuenta cuantas lineas ocupara un texto con la tipografia indicada.
     * Sirve para reservar espacio antes de dibujar y evitar desbordes.
     */
    private function estimarLineas(string $texto, float $ancho, string $estilo, float $pt): int // Estima numero de lineas
    {
        $this->SetFont('Times', $estilo, $pt); // Configura fuente para medir
        $linea = ''; // Linea en construccion
        $n = 1; // Contador de lineas (al menos 1)
        foreach (preg_split('/\s+/', trim($texto)) as $palabra) { // Recorre palabras
            if ($palabra === '') continue; // Salta vacias
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra); // Prueba agregar
            if ($this->GetStringWidth($prueba) <= $ancho) { // Si cabe
                $linea = $prueba; // Agrega palabra
            } else { // Si no cabe
                $n++; // Nueva linea
                $linea = $palabra; // Inicia con la palabra
            }
        }
        return $n; // Devuelve el numero estimado de lineas
    }

    /**
     * Nota de figura segun APA: "Nota." en cursiva seguida del texto
     * en redonda, con sangria francesa y texto JUSTIFICADO (todas las
     * lineas al mismo ancho, excepto la ultima).
     */
    private function notaApa(string $texto): void // Dibuja la nota de figura con sangria francesa
    {
        $prefijo   = 'Nota. '; // Prefijo cursiva de la nota
        $wPrefijo  = $this->GetStringWidth($prefijo) + 1; // Ancho del prefijo mas espacio
        $anchoUtil = $this->fw - $wPrefijo;   // todas las lineas respetan la sangria francesa

        $palabras = preg_split('/\s+/', trim($texto)); // Divide el texto en palabras
        $lineas   = []; // Acumula lineas
        $linea    = ''; // Linea en construccion
        $limite   = $anchoUtil; // Ancho disponible por linea

        foreach ($palabras as $palabra) { // Recorre palabras
            if ($palabra === '') continue; // Salta vacias
            $prueba = $linea === '' ? $palabra : ($linea . ' ' . $palabra); // Prueba agregar
            if ($this->GetStringWidth($prueba) <= $limite) { // Si cabe
                $linea = $prueba; // Agrega palabra
            } else { // Si no cabe
                if ($linea !== '') $lineas[] = $linea; // Guarda linea
                $linea  = $palabra; // Nueva linea
                $limite = $anchoUtil; // Restaura limite
            }
        }
        if ($linea !== '') $lineas[] = $linea; // Guarda ultima linea

        $total = count($lineas); // Total de lineas
        foreach ($lineas as $i => $l) { // Dibuja cada linea
            $this->SetX($this->lMargin); // Posicion X al margen izquierdo
            if ($i === 0) { // Primera linea lleva prefijo
                $this->SetFont('Times', 'I', 10); // Fuente cursiva 10 para "Nota."
                $this->Cell($wPrefijo, self::ALTO_NOTA, $prefijo, 0, 0, 'L'); // Imprime prefijo
                $this->SetFont('Times', '', 10); // Fuente normal 10 para el texto
            } else { // Lineas siguientes
                $this->SetX($this->lMargin + $wPrefijo);  // sangria francesa
            }

            if ($i === $total - 1) { // Ultima linea
                // Ultima linea: izquierda normal
                $this->Cell($this->fw, self::ALTO_NOTA, $l, 0, 1, 'L'); // Imprime sin justificar
            } else { // Lineas intermedias
                $this->lineaJustificadaNota($l, $anchoUtil); // Justifica la linea
            }
        }
    }

    /**
     * Linea justificada de una nota (10 pt, alto de linea menor).
     * Misma tecnica Tw que lineaJustificada pero con ALTO_NOTA.
     */
    private function lineaJustificadaNota(string $texto, float $anchoObjetivo): void // Justifica linea de nota
    {
        $numEspacios = substr_count($texto, ' '); // Cuenta espacios

        if ($numEspacios < 1) { // Si no hay espacios
            $this->Cell($this->fw, self::ALTO_NOTA, $texto, 0, 1, 'L'); // Imprime sin justificar
            return; // Sale
        }

        $anchoTexto = $this->GetStringWidth($texto); // Ancho real del texto
        $extraMm    = max(0, $anchoObjetivo - $anchoTexto); // Espacio extra
        $twPt       = ($extraMm / $numEspacios) * $this->k; // Espaciado Tw

        $x = $this->x; // Posicion X actual
        $y = $this->y; // Posicion Y actual
        $h = self::ALTO_NOTA; // Alto de linea de nota
        $baseY = ($this->h - $y - 0.5 * $h + 0.3 * $this->FontSize) * $this->k; // Y base en puntos

        $s  = sprintf('BT %.2f %.2f Td %.3F Tw ', $x * $this->k, $baseY, $twPt); // Operadores PDF
        $s .= '(' . $this->_escape($texto) . ') Tj ET'; // Texto y fin
        if ($this->ColorFlag) { // Si hay color activo
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q'; // Envuelve con color
        }
        $this->_out($s); // Emite al PDF
        $this->_out('0 Tw'); // Restaura espaciado normal

        $this->y += $h; // Avanza Y
        $this->x = $this->lMargin; // Reinicia X al margen
    }
} // Fin de la clase PdfExport
