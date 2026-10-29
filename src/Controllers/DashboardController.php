<?php
// ============================================================
// CONTROLADOR DEL DASHBOARD
// ============================================================

namespace App\Controllers;

use App\Models\DashboardModel;
use App\Export\PdfExport;
use App\Export\ExcelExport;

class DashboardController
{
    private DashboardModel $model;
    private PdfExport $pdf;
    private ExcelExport $excel;

    public function __construct()
    {
        $this->model = new DashboardModel();
        $this->pdf = new PdfExport();
        $this->excel = new ExcelExport();
    }

    // Pagina principal del dashboard
    public function index(): void
    {
        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';

        $stats = $this->model->getEstadisticasAdmin($rolEjecutor);

        $datos = [
            'estadisticas' => $stats,
            'rol' => $rolEjecutor,
        ];

        $this->render('dashboard', $datos);
    }

    // API para los graficos (JSON)
    public function apiChart(string $tipo): void
    {
        header('Content-Type: application/json');

        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
        $estado  = $_GET['estado']  ?? '';
        $cliente = $_GET['cliente'] ?? '';
        $qr      = $_GET['qr']      ?? '';
        $periodo = $_GET['periodo'] ?? 'TODOS';
        if (empty($periodo)) $periodo = 'TODOS';

        $datos = [];

        switch ($tipo) {
            case 'barras':
                $datos = $this->model->getIngresosPorEstacion($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;

            case 'histograma':
                $datos = $this->model->getDuracionAlquileres($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;

            case 'poligono':
                $datos = $this->model->getAlquileresPorPeriodo($rolEjecutor, $periodo, $estado, $cliente, $qr);
                break;

            case 'sectores':
                $datos = $this->model->getAlquileresPorEstado($rolEjecutor, $periodo, $estado, $cliente, $qr);
                break;

            case 'radar':
                $datos = $this->model->getIndicadoresRadar($rolEjecutor);
                break;

            case 'alquileres':
                // Filas para la tabla (vista SPA) con los mismos filtros
                $datos = $this->model->getAlquileres($rolEjecutor, $estado, $cliente, $qr, 1000, 0, $periodo);
                break;

            case 'estadisticas':
                $datos = $this->model->getEstadisticasAdmin($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;
        }

        echo json_encode(['success' => true, 'data' => $datos]);
        exit;
    }

    // Exportar a PDF - Reporte ejecutivo con gráficas
    public function exportarPdf(): void
    {
        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
        
        // Si es POST, obtener datos del cuerpo de la solicitud
        $estado  = '';
        $cliente = '';
        $qr      = '';
        $periodo = 'TODOS';
        $imagens = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $estado  = $input['estado']  ?? '';
            $cliente = $input['cliente'] ?? '';
            $qr      = $input['qr']      ?? '';
            $periodo = $input['periodo'] ?? 'TODOS';
            if (empty($periodo)) $periodo = 'TODOS';
            $imagens = $input['imagenes'] ?? [];
        } else {
            // Modo GET (compatibilidad hacia atrás)
            $estado  = $_GET['estado']  ?? '';
            $cliente = $_GET['cliente'] ?? '';
            $qr      = $_GET['qr']      ?? '';
            $periodo = $_GET['periodo'] ?? 'TODOS';
        }
        
        // Estadisticas FILTRADAS: el resumen ejecutivo del PDF debe reflejar
        // los mismos filtros que se ven en los graficos del dashboard
        $stats = $this->model->getEstadisticasAdmin($rolEjecutor, $estado, $cliente, $qr, $periodo);

        $this->pdf->generarReporteEjecutivo($stats, $estado, $periodo, $cliente, $qr, [], $imagens);
    }

    // Exportar a Excel (CSV con separador ; para Excel en español)
    public function exportarExcel(): void
    {
        try {
            $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
            $estado  = $_GET['estado']  ?? '';
            $cliente = $_GET['cliente'] ?? '';
            $qr      = $_GET['qr']      ?? '';
            $periodo = $_GET['periodo'] ?? 'TODOS';
            if (empty($periodo)) $periodo = 'TODOS';

            $alquileres = $this->model->getAlquileres($rolEjecutor, $estado, $cliente, $qr, 10000, 0, $periodo);

            $this->excel->create($alquileres);

            $this->excel->Output('reporte_alquileres_' . date('Ymd_His') . '.csv', 'CSV');
        } catch (\Throwable $e) {
            error_log('Error exportando Excel: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error generando el reporte Excel']);
        }
        exit;
    }

    // Renderizar una vista
    private function render(string $vista, array $datos): void
    {
        extract($datos);
        require __DIR__ . '/../Views/' . $vista . '.php';
    }
}
