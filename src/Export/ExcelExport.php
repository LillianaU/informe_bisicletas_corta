<?php
// ============================================================
// EXPORTACION A EXCEL/CSV - REPORTES (CSV nativo, sin librerías)
// ============================================================

namespace App\Export;

class ExcelExport
{
    private array $rows = [];

    public function __construct()
    {
        // Encabezado por defecto
    }

    // Crea el reporte con los alquileres
    public function create(array $alquileres): void
    {
        $this->rows = [];

        // Encabezados
        $this->rows[] = ['ID', 'UUID', 'Fecha Inicio', 'Fecha Fin', 'Costo Total', 'Estado', 'Cliente', 'Bicicleta', 'Estacion Origen', 'Estacion Destino'];

        foreach ($alquileres as $a) {
            $this->rows[] = [
                (int)($a['id_alquiler'] ?? 0),
                $a['uuid_alquiler'] ?? '',
                $a['fecha_inicio'] ?? '',
                $a['fecha_fin'] ?? '',
                (float)($a['costo_total'] ?? 0),
                $a['estado_alquiler'] ?? '',
                $a['cliente'] ?? '',
                $a['codigo_qr'] ?? '',
                $a['estacion_origen'] ?? '',
                $a['estacion_destino'] ?? '',
            ];
        }
    }

public function Output(string $nombreArchivo, string $formato = 'CSV'): void
    {
        // Solo se soporta formato CSV
        $nombreArchivo = str_replace('.xlsx', '.csv', $nombreArchivo);
        $nombreArchivo = str_replace('.xls', '.csv', $nombreArchivo);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM UTF-8 para Excel
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        foreach ($this->rows as $row) {
            fputcsv($output, $row, ';'); // Punto y coma para Excel en español
        }
        fclose($output);
        exit;
    }
}