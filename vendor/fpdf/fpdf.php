<?php
// ============================================================
// FPDF - Complete working implementation with proper image support
// ============================================================

class FPDF
{
    protected $page = 0;
    protected $n = 0;
    protected $offsets = [];
    protected $buffer = '';
    protected $pages = [];
    protected $state = 0;
    protected $k;
    protected $w = 0; protected $h = 0;
    protected $fw = 0; protected $fh = 0;
    protected $fwPt = 0; protected $fhPt = 0;
    protected $wPt = 0; protected $hPt = 0;
    protected $lMargin = 25.4; protected $rMargin = 25.4;
    protected $tMargin = 25.4; protected $bMargin = 25.4;
    protected $cMargin = 0;
    protected $x = 0; protected $y = 0;
    protected $lasth = 0;
    protected $LineWidth = 0.2;
    protected $CoreFonts = ['courier','helvetica','times','symbol','zapfdingbats'];
    protected $fonts = []; protected $FontFiles = []; protected $diffs = [];
    protected $FontFamily = 'Helvetica'; protected $FontStyle = ''; protected $FontSizePt = 12; protected $FontSize = 12;
    protected $DrawColor = '0 G'; protected $FillColor = '0 g'; protected $TextColor = '0 g'; protected $ColorFlag = false;
    protected $ws = 0;
    protected $images = []; protected $imageCounter = 0;
    protected $PageLinks = []; protected $links = [];
    protected $underline = false; protected $AutoPageBreak = true; protected $PageBreakTrigger = 0;
    protected $InHeader = false; protected $InFooter = false;
    protected $ZoomMode = 'fullpage'; protected $LayoutMode = 'single';
    protected $title = ''; protected $subject = ''; protected $author = ''; protected $keywords = '';
    protected $creator = 'FPDF'; protected $AliasNbPages = '{nb}'; protected $PDFVersion = '1.3';
    protected $orientation = 'P';
    protected $content = '';
    protected $catalogObj = 0;
    protected $infoObj = 0;
    protected $pagesObj = 0;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        $this->_dochecks();
        $this->_init($orientation, $unit, $size);
    }

    protected function _dochecks()
    {
        if (1.1 == 1) $this->_errordoc('Don\'t alter locale with setlocale(). Use localeconv().');
        if (sprintf('%.1f', 1.0) != '1.0') $this->_errordoc('sprintf is broken.');
    }

    protected function _init($orientation, $unit, $size)
    {
        $this->_setunit($unit);
        $this->_setorientation($orientation);
        $this->_setsize($size);
        $this->PageBreakTrigger = $this->h - $this->bMargin;
        $this->x = $this->lMargin; $this->y = $this->tMargin;
        $this->fonts = []; $this->FontFiles = []; $this->diffs = [];
        $this->images = []; $this->imageCounter = 0;
        $this->PageLinks = []; $this->links = [];
        $this->offsets = [0]; $this->buffer = ''; $this->pages = [];
        $this->state = 0; $this->page = 0; $this->n = 0;
        $this->FontFamily = 'Helvetica'; $this->FontStyle = '';
        $this->FontSizePt = 12; $this->FontSize = 12;
        $this->DrawColor = '0 G'; $this->FillColor = '0 g'; $this->TextColor = '0 g'; $this->ColorFlag = false;
        $this->ws = 0; $this->content = '';
    }

    protected function _setunit($unit)
    {
        $unit = strtolower($unit);
        if ($unit == 'pt') $this->k = 1;
        elseif ($unit == 'mm') $this->k = 72 / 25.4;
        elseif ($unit == 'cm') $this->k = 72 / 2.54;
        elseif ($unit == 'in') $this->k = 72;
        else $this->_errordoc('Incorrect unit: ' . $unit);
    }

    protected function _setorientation($orientation)
    {
        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') { $this->orientation = 'P'; $this->w = 210; $this->h = 297; }
        elseif ($orientation == 'l' || $orientation == 'landscape') { $this->orientation = 'L'; $this->w = 297; $this->h = 210; }
        else $this->_errordoc('Incorrect orientation: ' . $orientation);
        $this->fw = $this->w - $this->lMargin - $this->rMargin;
        $this->fh = $this->h - $this->tMargin - $this->bMargin;
        $this->PageBreakTrigger = $this->h - $this->bMargin;
    }

    protected function _setsize($size)
    {
        if (is_array($size)) { $this->w = $size[0]; $this->h = $size[1]; }
        else {
            $size = strtoupper($size);
            if ($size == 'A3') { $w = 297; $h = 420; }
            elseif ($size == 'A4') { $w = 210; $h = 297; }
            elseif ($size == 'A5') { $w = 148; $h = 210; }
            elseif ($size == 'LETTER') { $w = 215.9; $h = 279.4; }
            elseif ($size == 'LEGAL') { $w = 215.9; $h = 355.6; }
            else $this->_errordoc('Incorrect page size: ' . $size);
            if ($this->orientation == 'P') { $this->w = $w; $this->h = $h; }
            else { $this->w = $h; $this->h = $w; }
        }
        $this->fw = $this->w - $this->lMargin - $this->rMargin;
        $this->fh = $this->h - $this->tMargin - $this->bMargin;
        $this->PageBreakTrigger = $this->h - $this->bMargin;
    }

    protected function _out($s)
    {
        if ($this->state == 2) $this->pages[$this->page] .= $s . "\n";
        else $this->content .= $s . "\n";
    }

    protected function _errordoc($msg) { die('<b>FPDF error:</b> ' . $msg); }

    public function SetAutoPageBreak($auto, $margin = 0)
    {
        $this->AutoPageBreak = $auto; $this->bMargin = $margin; $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetFont($family, $style = '', $size = 0)
    {
        $family = strtolower($family); if ($family == '') $family = 'helvetica';
        $style = strtoupper($style); if ($style == 'IB') $style = 'BI';
        if ($size == 0) $size = $this->FontSizePt;
        $this->FontFamily = $family; $this->FontStyle = $style; $this->FontSizePt = $size; $this->FontSize = $size / $this->k;
        if ($this->state == 2) $this->_out(sprintf('BT /F%d %.2f Tf ET', $this->_getfontid($family, $style), $this->FontSizePt));
    }

    protected function _getfontid($family, $style)
    {
        $key = $family . $style;
        if (isset($this->fonts[$key])) return $this->fonts[$key]['i'];
        $i = count($this->fonts) + 1;
        $this->fonts[$key] = ['i' => $i, 'family' => $family, 'style' => $style];
        return $i;
    }

    public function AddPage()
    {
        if ($this->state == 3) $this->_errordoc('The document is closed');
        $this->_newpage();
        $this->x = $this->lMargin; $this->y = $this->tMargin;
        $this->_out('q');
        $this->InHeader = true; $this->Header(); $this->InHeader = false;
        $this->_out('Q');
        // Re-emitir colores vigentes: el estado grafico NO persiste entre paginas
        if ($this->DrawColor != '0 G') $this->_out($this->DrawColor);
        if ($this->FillColor != '0 g') $this->_out($this->FillColor);
    }

    protected function _newpage()
    {
        $this->page++; $this->pages[$this->page] = ''; $this->state = 2;
    }

    public function Header() {}
    public function Footer() {}

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak) $this->AddPage();
        if ($w == 0) $w = $this->fw - $this->x + $this->lMargin;
        $s = '';
        if ($fill || $border == 1) { $op = $fill ? 'f' : 'S'; $s = sprintf('%.2f %.2f %.2f %.2f re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op); }
        if (is_string($border)) { $b = []; if (strpos($border, 'L') !== false) $b[] = 'L'; if (strpos($border, 'T') !== false) $b[] = 'T'; if (strpos($border, 'R') !== false) $b[] = 'R'; if (strpos($border, 'B') !== false) $b[] = 'B'; }
        else $b = $border == 1 ? ['L', 'T', 'R', 'B'] : [];
        foreach ($b as $side) {
            switch ($side) {
                case 'L': $s .= sprintf('%.2f %.2f m %.2f %.2f l S ', $this->x * $k, ($this->h - $this->y) * $k, $this->x * $k, ($this->h - $this->y - $h) * $k); break;
                case 'T': $s .= sprintf('%.2f %.2f m %.2f %.2f l S ', $this->x * $k, ($this->h - $this->y) * $k, ($this->x + $w) * $k, ($this->h - $this->y) * $k); break;
                case 'R': $s .= sprintf('%.2f %.2f m %.2f %.2f l S ', ($this->x + $w) * $k, ($this->h - $this->y) * $k, ($this->x + $w) * $k, ($this->h - $this->y - $h) * $k); break;
                case 'B': $s .= sprintf('%.2f %.2f m %.2f %.2f l S ', $this->x * $k, ($this->h - $this->y - $h) * $k, ($this->x + $w) * $k, ($this->h - $this->y - $h) * $k); break;
            }
        }
        if ($txt !== '') {
            if ($align == 'R') $dx = $w - $this->cMargin; elseif ($align == 'C') $dx = $w / 2; else $dx = $this->cMargin;
            if ($this->ColorFlag) $s .= 'q ' . $this->TextColor . ' ';
            $s .= sprintf('BT %.2f %.2f Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - $this->y - .5 * $h + .3 * $this->FontSize) * $k, $this->_escape($txt));
            if ($this->ColorFlag) $s .= ' Q';
        }
        if ($s) $this->_out($s);
        $this->lasth = $h;
        if ($ln > 0) { $this->y += $h; $this->x = $this->lMargin; } else $this->x += $w;
    }

    public function Ln($h = '') { $this->x = $this->lMargin; if (is_numeric($h)) $this->y += $h; else $this->y += $this->lasth; }
    public function GetStringWidth($s) {
        // Times: metricas reales de la fuente (per-mille) para un
        // calculo preciso del ancho; otras fuentes: aproximacion de 0.55 em
        if ($this->FontFamily === 'times') {
            $w = 0;
            for ($i = 0, $n = strlen($s); $i < $n; $i++) {
                $w += $this->_charWidthPt($s[$i]);
            }
            return $w * $this->FontSizePt / 1000 / $this->k;
        }
        return strlen($s) * $this->FontSizePt * 0.55 / $this->k;
    }

    /**
     * Ancho de un caracter de Times-Roman en unidades por mil (AFM estandar).
     */
    protected function _charWidthPt(string $c): int
    {
        static $times = [
            ' ' => 250, '!' => 333, '"' => 408, '#' => 500, '$' => 500, '%' => 833,
            '&' => 778, "'" => 180, '(' => 333, ')' => 333, '*' => 500, '+' => 564,
            ',' => 250, '-' => 333, '.' => 250, '/' => 278,
            '0' => 500, '1' => 500, '2' => 500, '3' => 500, '4' => 500,
            '5' => 500, '6' => 500, '7' => 500, '8' => 500, '9' => 500,
            ':' => 278, ';' => 278, '<' => 564, '=' => 564, '>' => 564, '?' => 444,
            '@' => 921,
            'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611, 'F' => 556,
            'G' => 722, 'H' => 722, 'I' => 333, 'J' => 389, 'K' => 722, 'L' => 611,
            'M' => 889, 'N' => 722, 'O' => 722, 'P' => 556, 'Q' => 722, 'R' => 667,
            'S' => 556, 'T' => 611, 'U' => 722, 'V' => 556, 'W' => 944, 'X' => 722,
            'Y' => 722, 'Z' => 611,
            '[' => 333, '\\' => 278, ']' => 333, '^' => 469, '_' => 500, '`' => 333,
            'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333,
            'g' => 500, 'h' => 500, 'i' => 278, 'j' => 278, 'k' => 500, 'l' => 278,
            'm' => 778, 'n' => 500, 'o' => 500, 'p' => 500, 'q' => 500, 'r' => 333,
            's' => 389, 't' => 278, 'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500,
            'y' => 500, 'z' => 444,
            '{' => 480, '|' => 200, '}' => 480, '~' => 541,
        ];
        if ($c === "\xE2\x82\xAC") return 500;   // euro (UTF-8)
        return $times[$c] ?? 500;               // desconocido: ~0.5 em
    }
    public function SetDrawColor($r, $g = -1, $b = -1) {
        if ($g == -1) $this->DrawColor = sprintf('%.3F G', $r / 255);
        else $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        if ($this->page > 0) $this->_out($this->DrawColor);
    }
    public function SetFillColor($r, $g = -1, $b = -1) {
        if ($g == -1) $this->FillColor = sprintf('%.3F g', $r / 255);
        else $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
        if ($this->page > 0) $this->_out($this->FillColor);
    }
    public function SetTextColor($r, $g = -1, $b = -1) {
        if ($g == -1) $this->TextColor = sprintf('%.3F g', $r / 255);
        else $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }
    public function SetLineWidth($w) { $this->_out(sprintf('%.2f w', $w * $this->k)); }
    public function SetX($x) { $this->x = $x; }
    public function Line($x1, $y1, $x2, $y2) { $this->_out(sprintf('%.2f %.2f m %.2f %.2f l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k)); }
    public function Rect($x, $y, $w, $h, $style = '') {
        $this->_out(sprintf('%.2f %.2f %.2f %.2f re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $style ? $style : 'S'));
    }
    public function RoundedRect($x, $y, $w, $h, $r, $style = '') { $this->Rect($x, $y, $w, $h, $style); }

    /**
     * Elipse aproximada con 4 curvas Bezier. Estilos: D=contorno, F=relleno, DF/B=ambos
     */
    public function Ellipse($x, $y, $rx, $ry, $style = 'D') {
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF' || $style == 'B') $op = 'B';
        else $op = 'S';
        $k = $this->k;
        $b = 0.5522847498;          // constante de aproximacion Bezier circular
        $px = $b * $rx; $py = $b * $ry;
        $cy = $this->h - $y;         // invertir eje Y
        // Punto inicial: extremo derecho
        $this->_out(sprintf('%.2f %.2f m', ($x + $rx) * $k, $cy * $k));
        // Arco 1: derecha -> abajo
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x + $rx) * $k, ($cy + $py) * $k, ($x + $px) * $k, ($cy + $ry) * $k,
            $x * $k, ($cy + $ry) * $k));
        // Arco 2: abajo -> izquierda
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x - $px) * $k, ($cy + $ry) * $k, ($x - $rx) * $k, ($cy + $py) * $k,
            ($x - $rx) * $k, $cy * $k));
        // Arco 3: izquierda -> arriba
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x - $rx) * $k, ($cy - $py) * $k, ($x - $px) * $k, ($cy - $ry) * $k,
            $x * $k, ($cy - $ry) * $k));
        // Arco 4: arriba -> derecha
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x + $px) * $k, ($cy - $ry) * $k, ($x + $rx) * $k, ($cy - $py) * $k,
            ($x + $rx) * $k, $cy * $k));
        $this->_out($op);
    }
    public function Circle($x, $y, $r, $style = 'D') { $this->Ellipse($x, $y, $r, $r, $style); }
    public function Image($file, $x, $y, $w = 0, $h = 0, $type = '', $link = '') {
        if (!file_exists($file)) return false;
        $info = getimagesize($file);
        if (!$info) return false;
        if ($w == 0) $w = $info[0] * 0.264583;
        if ($h == 0) $h = $w * ($info[1] / $info[0]);
        $this->imageCounter++;
        $idx = $this->imageCounter;
        // Store file data immediately (temp file may be deleted later)
        $this->images[$idx] = [
            'file' => $file,
            'data' => file_get_contents($file),
            'w' => $info[0],
            'h' => $info[1],
            'displayW' => $w,
            'displayH' => $h,
            'type' => $info[2],
        ];
        $this->_out('q ' . ($w * $this->k) . ' 0 0 ' . ($h * $this->k) . ' ' . ($x * $this->k) . ' ' . (($this->h - $y - $h) * $this->k) . ' cm /I' . $idx . ' Do Q');
        return ['w' => $w, 'h' => $h];
    }
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false) {
        if ($w == 0) $w = $this->fw;
        $align = $align == 'J' ? 'L' : $align; // justificado no soportado por Cell
        foreach (explode("\n", $txt) as $par) {
            $words = preg_split('/\s+/u', trim($par));
            $lines = [];
            $line = '';
            foreach ($words as $word) {
                if ($word === '') continue;
                $test = $line === '' ? $word : ($line . ' ' . $word);
                if ($this->GetStringWidth($test) <= $w - 2 * $this->cMargin) {
                    $line = $test;
                } else {
                    if ($line !== '') $lines[] = $line;
                    $line = $word;
                }
            }
            if ($line !== '') $lines[] = $line;
            foreach ($lines as $l) { $this->Cell($w, $h, $l, $border, 1, $align, $fill); }
        }
    }
    public function SetY($y) { $this->y = $y; }
    public function GetY() { return $this->y; }
    public function PageNo() { return $this->page; }
    public function SetFontSize($size) { $this->FontSizePt = $size; $this->FontSize = $size / $this->k; if ($this->state == 2) $this->_out(sprintf('BT /F%d %.2f Tf ET', $this->_getfontid($this->FontFamily, $this->FontStyle), $this->FontSizePt)); }
    protected function _escape($s) { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s); }

    public function Output($dest = 'I', $name = '', $isUTF8 = false)
    {
        if ($this->state == 3) $this->_errordoc('The document is closed');
        $this->Close();
        $pdf = $this->_buildpdf();
        if ($dest == 'D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
        } elseif ($dest == 'F') { file_put_contents($name, $pdf); }
        elseif ($dest == 'S') { return $pdf; }
        else {
            header('Content-Type: application/pdf');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $pdf;
        }
    }

    protected function Close()
    {
        if ($this->state == 3) return;
        $this->_endpage();
        
        // 1. Create Pages tree, Page, and Content stream objects
        $this->_putpages();
        $pagesObj = $this->pagesObj;  // stored during _putpages
        
        // 2. Create font objects
        $this->_putfonts();
        
        // 3. Create image objects (convert PNG to JPEG)
        if (count($this->images)) $this->_putimages();
        
        // 4. Create Resources dictionary as object 2
        //    (save current n, set to 1, create obj 2, restore n)
        $savedN = $this->n;
        $this->n = 1;
        $this->_newobj();
        $this->_out('2 0 obj');
        $this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');
        foreach ($this->fonts as $font) {
            $this->_out('/F' . $font['i'] . ' ' . $font['objNum'] . ' 0 R');
        }
        $this->_out('>>');
        if (count($this->images)) {
            $this->_out('/XObject <<');
            foreach ($this->images as $idx => $img) {
                $this->_out('/I' . $idx . ' ' . $img['objNum'] . ' 0 R');
            }
            $this->_out('>>');
        }
        $this->_out('>>');
        $this->_out('endobj');
        $this->n = $savedN;
        
        // 5. Create Info object
        $this->_putinfo();
        $this->infoObj = $this->n;
        
        // 6. Create Catalog object
        $this->_putcatalog($pagesObj);
        $this->catalogObj = $this->n;
        
        // 7. No trailer here - handled in _buildpdf
        
        $this->state = 3;
    }

    protected function _endpage()
    {
        if ($this->InFooter) return;
        $this->InFooter = true; $this->y = $this->h - $this->bMargin;
        $this->Footer(); $this->InFooter = false;
        $this->_out('Q'); $this->state = 1;
    }

    protected function _newobj()
    {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->content);
    }

    protected function _putpages()
    {
        $nb = $this->page;
        // Pages tree (obj 1) - create first
        $this->_newobj();
        $pagesObj = $this->n;
        $this->pagesObj = $pagesObj;
        $this->_out($this->n . ' 0 obj');
        $this->_out('<</Type /Pages /Kids [');
        for ($i = 1; $i <= $nb; $i++) {
            $pageObjNum = $pagesObj + 2 + ($i - 1) * 2;  // Skip obj 2 (Resources)
            $this->_out($pageObjNum . ' 0 R');
        }
        $this->_out('] /Count ' . $nb . '>>');
        $this->_out('endobj');

        // Skip object 2 (reserved for Resources)
        $this->n = 2;

        for ($i = 1; $i <= $nb; $i++) {
            // Page object
            $this->_newobj();
            $pageObj = $this->n;
            $this->_out($this->n . ' 0 obj');
            $this->_out('<</Type /Page /Parent ' . $pagesObj . ' 0 R');
            if (!empty($this->PageLinks[$i])) $this->_out('/Annots ' . ($this->n + 1) . ' 0 R');
            $this->_out('/Resources 2 0 R');
            $this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->_out('endobj');

            // Page content stream
            $this->_newobj();
            $this->_out($this->n . ' 0 obj');
            $content = $this->pages[$i];
            $this->_out('<</Length ' . strlen($content) . '>>');
            $this->_out('stream');
            $this->_out($content);
            $this->_out('endstream');
            $this->_out('endobj');
        }
    }

    protected function _putfonts()
    {
        foreach ($this->fonts as $key => $font) {
            $this->_newobj();
            $this->_out($this->n . ' 0 obj');
            $this->_out('<</Type /Font /BaseFont /' . $this->_baseFontName($font['family'], $font['style']) . ' /Subtype /Type1 /Encoding /WinAnsiEncoding>>');
            $this->_out('endobj');
            $this->fonts[$key]['objNum'] = $this->n;
        }
    }

    /**
     * Nombre canonico de la fuente Type1 segun el estandar PDF.
     * Evita nombres abreviados (p.ej. "HelveticaB") que algunos visores
     * no reconocen al renderizar o extraer texto.
     */
    protected function _baseFontName(string $family, string $style): string
    {
        $map = [
            'courier'    => ['' => 'Courier', 'B' => 'Courier-Bold', 'I' => 'Courier-Oblique', 'BI' => 'Courier-BoldOblique'],
            'helvetica'  => ['' => 'Helvetica', 'B' => 'Helvetica-Bold', 'I' => 'Helvetica-Oblique', 'BI' => 'Helvetica-BoldOblique'],
            'times'      => ['' => 'Times-Roman', 'B' => 'Times-Bold', 'I' => 'Times-Italic', 'BI' => 'Times-BoldItalic'],
            'symbol'     => ['' => 'Symbol'],
            'zapfdingbats' => ['' => 'ZapfDingbats'],
        ];
        return $map[$family][$style] ?? ($map[$family][''] ?? $family);
    }

    protected function _putimages()
    {
        foreach ($this->images as $idx => &$img) {
            $this->_newobj();
            $img['objNum'] = $this->n;
            $this->_out($this->n . ' 0 obj');
            $data = $img['data'];
            $info = getimagesizefromstring($data);
            $jpegData = false;

            if ($info && $info[2] === IMAGETYPE_JPEG) {
                // Already JPEG: embed directly
                $jpegData = $data;
            } else {
                // PNG (or anything else): convert via GD, flattening
                // transparency onto a WHITE background (JPEG has no alpha;
                // without this, transparent areas render as black)
                $src = $info ? @imagecreatefromstring($data) : false;
                if ($src) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $dst = imagecreatetruecolor($w, $h);
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefill($dst, 0, 0, $white);
                    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                    ob_start();
                    imagejpeg($dst, null, 90);
                    $jpegData = ob_get_clean();
                    imagedestroy($src);
                    imagedestroy($dst);
                }
            }

            if ($jpegData === false || $jpegData === '') {
                // Cannot embed: write a harmless 1x1 gray placeholder
                // instead of a corrupt stream, so the PDF stays valid
                $this->_out('<</Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1>>');
                $this->_out('stream');
                $this->_out("\xCC");
                $this->_out('endstream');
            } else {
                $this->_out('<</Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpegData) . '>>');
                $this->_out('stream');
                $this->_out($jpegData);
                $this->_out('endstream');
            }
            $this->_out('endobj');
        }
    }

    protected function _putinfo()
    {
        $this->_newobj();
        $this->_out($this->n . ' 0 obj');
        $this->_out('<</Title (' . $this->_escape($this->title) . ')');
        $this->_out('/Subject (' . $this->_escape($this->subject) . ')');
        $this->_out('/Author (' . $this->_escape($this->author) . ')');
        $this->_out('/Keywords (' . $this->_escape($this->keywords) . ')');
        $this->_out('/Creator (' . $this->_escape($this->creator) . ')');
        $this->_out('/Producer (FPDF ' . $this->PDFVersion . ')');
        $this->_out('/CreationDate (' . $this->_datestring() . ')');
        $this->_out('>>'); $this->_out('endobj');
    }

    protected function _putcatalog($pagesObj)
    {
        $this->_newobj();
        $this->_out($this->n . ' 0 obj');
        $this->_out('<</Type /Catalog /Pages ' . $pagesObj . ' 0 R');
        $firstPageObj = $pagesObj + 2; // First page object is Pages tree + 2 (skip obj 2 = Resources)
        if ($this->ZoomMode == 'fullpage') $this->_out('/OpenAction [' . $firstPageObj . ' 0 R /Fit]');
        elseif ($this->ZoomMode == 'fullwidth') $this->_out('/OpenAction [' . $firstPageObj . ' 0 R /FitH null]');
        elseif ($this->ZoomMode == 'real') $this->_out('/OpenAction [' . $firstPageObj . ' 0 R /XYZ null null 1]');
        elseif (!is_string($this->ZoomMode)) $this->_out('/OpenAction [' . $firstPageObj . ' 0 R /XYZ null null ' . sprintf('%.2f', $this->ZoomMode / 100) . ']');
        if ($this->LayoutMode == 'single') $this->_out('/PageLayout /SinglePage');
        elseif ($this->LayoutMode == 'continuous') $this->_out('/PageLayout /OneColumn');
        elseif ($this->LayoutMode == 'two') $this->_out('/PageLayout /TwoColumnLeft');
        $this->_out('>>'); $this->_out('endobj');
    }

    protected function _puttrailer()
    {
        $this->_out('trailer');
        $this->_out('<</Size ' . ($this->n + 1) . ' /Root ' . $this->n . ' 0 R /Info ' . ($this->n - 1) . ' 0 R>>');
    }

    protected function _datestring()
    {
        $date = date('YmdHis');
        return 'D:' . substr($date, 0, 4) . substr($date, 4, 2) . substr($date, 6, 2) . substr($date, 8, 2) . substr($date, 10, 2) . substr($date, 12, 2);
    }

    protected function _buildpdf()
    {
        $header = '%PDF-1.3' . "\n";
        $base = strlen($header);
        $pdf = $header . $this->content;
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($this->n + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->n; $i++) {
            // Object offsets were recorded relative to $this->content;
            // the final file prepends the header, so add its length
            $offset = ($this->offsets[$i] ?? 0) + $base;
            $pdf .= sprintf('%010d 00000 n ' . "\n", $offset);
        }
        $pdf .= "trailer\n";
        $pdf .= '<</Size ' . ($this->n + 1) . ' /Root ' . $this->catalogObj . ' 0 R /Info ' . $this->infoObj . ' 0 R>>' . "\n";
        $pdf .= "startxref\n$xrefPos\n%%EOF\n";
        return $pdf;
    }
}