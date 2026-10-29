<?php
// ============================================================
// EXPORTACION A EXCEL/CSV - REPORTES (CSV nativo, sin librerías)
// ============================================================

// Declara el espacio de nombres para la clase de exportación
namespace App\Export;

// Clase ExcelExport genera reportes en formato CSV compatibles con Excel
class ExcelExport
{
    // Array que almacena las filas del reporte (incluye encabezados)
    private array $rows = [];

    // Constructor de la clase (sin operaciones adicionales)
    public function __construct()
    {
        // Encabezado por defecto
    }

    // Crea el reporte con los alquileres recibidos
    public function create(array $alquileres): void
    {
        // Reinicia las filas para un nuevo reporte
        $this->rows = [];

        // Define la fila de encabezados de columnas
        $this->rows[] = ['ID', 'UUID', 'Fecha Inicio', 'Fecha Fin', 'Costo Total', 'Estado', 'Cliente', 'Bicicleta', 'Estacion Origen', 'Estacion Destino'];

        // Itera sobre cada alquiler para construir las filas de datos
        foreach ($alquileres as $a) {
            $this->rows[] = [
                // ID del alquiler convertido a entero (0 si no existe)
                (int)($a['id_alquiler'] ?? 0),
                // UUID del alquiler o cadena vacía
                $a['uuid_alquiler'] ?? '',
                // Fecha de inicio o cadena vacía
                $a['fecha_inicio'] ?? '',
                // Fecha de fin o cadena vacía
                $a['fecha_fin'] ?? '',
                // Costo total convertido a float (0 si no existe)
                (float)($a['costo_total'] ?? 0),
                // Estado del alquiler o cadena vacía
                $a['estado_alquiler'] ?? '',
                // Nombre del cliente o cadena vacía
                $a['cliente'] ?? '',
                // Código QR de la bicicleta o cadena vacía
                $a['codigo_qr'] ?? '',
                // Estación de origen o cadena vacía
                $a['estacion_origen'] ?? '',
                // Estación de destino o cadena vacía
                $a['estacion_destino'] ?? '',
            ];
        }
    }

public function Output(string $nombreArchivo, string $formato = 'CSV'): void
    {
        // Solo se soporta formato CSV; normaliza extensiones .xlsx/.xls a .csv
        $nombreArchivo = str_replace('.xlsx', '.csv', $nombreArchivo);
        $nombreArchivo = str_replace('.xls', '.csv', $nombreArchivo);

        // Envía encabezado de tipo de contenido CSV con charset UTF-8
        header('Content-Type: text/csv; charset=UTF-8');
        // Indica al navegador que descargue el archivo con el nombre indicado
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        // Evita caché del navegador
        header('Pragma: no-cache');
        // Fecha de expiración inmediata para no cachear
        header('Expires: 0');

        // Escribe el BOM UTF-8 para que Excel interprete correctamente los acentos
        echo "\xEF\xBB\xBF";

        // Abre el flujo de salida estándar para escritura
        $output = fopen('php://output', 'w');
        // Escribe cada fila del reporte en el CSV
        foreach ($this->rows as $row) {
            // Usa punto y coma (;) como separador para Excel en español
            fputcsv($output, $row, ';'); // Punto y coma para Excel en español
        }
        // Cierra el flujo de salida
        fclose($output);
        // Finaliza la ejecución del script tras enviar el archivo
        exit;
    }
}
