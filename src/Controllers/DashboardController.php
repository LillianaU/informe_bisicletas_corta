<?php
// ============================================================
// CONTROLADOR DEL DASHBOARD
// ============================================================

// Declara el espacio de nombres para el controlador
namespace App\Controllers;

// Importa el modelo de datos del dashboard
use App\Models\DashboardModel;
// Importa la clase de exportación a PDF
use App\Export\PdfExport;
// Importa la clase de exportación a Excel/CSV
use App\Export\ExcelExport;

// Clase controladora del dashboard
class DashboardController
{
    // Propiedad para el modelo de datos
    private DashboardModel $model;
    // Propiedad para la exportación a PDF
    private PdfExport $pdf;
    // Propiedad para la exportación a Excel/CSV
    private ExcelExport $excel;

    // Constructor: inicializa las dependencias
    public function __construct()
    {
        // Instancia el modelo de datos
        $this->model = new DashboardModel();
        // Instancia el exportador de PDF
        $this->pdf = new PdfExport();
        // Instancia el exportador de Excel/CSV
        $this->excel = new ExcelExport();
    }

    // Pagina principal del dashboard
    public function index(): void
    {
        // Obtiene el rol del ejecutor desde la sesión, por defecto 'ADMIN'
        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';

        // Obtiene las estadísticas del administrador según el rol
        $stats = $this->model->getEstadisticasAdmin($rolEjecutor);

        // Prepara el array de datos para la vista
        $datos = [
            // Estadísticas obtenidas
            'estadisticas' => $stats,
            // Rol del ejecutor
            'rol' => $rolEjecutor,
        ];

        // Renderiza la vista 'dashboard' con los datos
        $this->render('dashboard', $datos);
    }

    // API para los graficos (JSON)
    public function apiChart(string $tipo): void
    {
        // Establece el encabezado de respuesta como JSON
        header('Content-Type: application/json');

        // Obtiene el rol del ejecutor desde la sesión, por defecto 'ADMIN'
        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
        // Obtiene el filtro de estado desde la URL (vacío por defecto)
        $estado  = $_GET['estado']  ?? '';
        // Obtiene el filtro de cliente desde la URL (vacío por defecto)
        $cliente = $_GET['cliente'] ?? '';
        // Obtiene el filtro de código QR desde la URL (vacío por defecto)
        $qr      = $_GET['qr']      ?? '';
        // Obtiene el periodo desde la URL (por defecto 'TODOS')
        $periodo = $_GET['periodo'] ?? 'TODOS';
        // Si el periodo viene vacío, se establece a 'TODOS'
        if (empty($periodo)) $periodo = 'TODOS';

        // Inicializa el array de datos a retornar
        $datos = [];

        // Despacha según el tipo de gráfico solicitado
        switch ($tipo) {
            case 'barras':
                // Obtiene ingresos por estación con los filtros aplicados
                $datos = $this->model->getIngresosPorEstacion($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;

            case 'histograma':
                // Obtiene la duración de los alquileres con los filtros aplicados
                $datos = $this->model->getDuracionAlquileres($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;

            case 'poligono':
                // Obtiene alquileres por periodo con los filtros aplicados
                $datos = $this->model->getAlquileresPorPeriodo($rolEjecutor, $periodo, $estado, $cliente, $qr);
                break;

            case 'sectores':
                // Obtiene alquileres por estado con los filtros aplicados
                $datos = $this->model->getAlquileresPorEstado($rolEjecutor, $periodo, $estado, $cliente, $qr);
                break;

            case 'radar':
                // Obtiene los indicadores para el gráfico radar según el rol
                $datos = $this->model->getIndicadoresRadar($rolEjecutor);
                break;

            case 'alquileres':
                // Filas para la tabla (vista SPA) con los mismos filtros
                $datos = $this->model->getAlquileres($rolEjecutor, $estado, $cliente, $qr, 1000, 0, $periodo);
                break;

            case 'estadisticas':
                // Obtiene las estadísticas administrativas con los filtros aplicados
                $datos = $this->model->getEstadisticasAdmin($rolEjecutor, $estado, $cliente, $qr, $periodo);
                break;
        }

        // Imprime la respuesta JSON con éxito y los datos
        echo json_encode(['success' => true, 'data' => $datos]);
        // Finaliza la ejecución del script
        exit;
    }

    // Exportar a PDF - Reporte ejecutivo con gráficas
    public function exportarPdf(): void
    {
        // Obtiene el rol del ejecutor desde la sesión, por defecto 'ADMIN'
        $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
        
        // Si es POST, obtener datos del cuerpo de la solicitud
        // Inicializa los filtros y las imágenes
        $estado  = '';
        $cliente = '';
        $qr      = '';
        $periodo = 'TODOS';
        $imagens = [];
        
        // Si la petición es POST, lee los datos del cuerpo JSON
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Decodifica el cuerpo JSON de la petición
            $input = json_decode(file_get_contents('php://input'), true);
            // Obtiene el estado del filtro desde el JSON
            $estado  = $input['estado']  ?? '';
            // Obtiene el cliente del filtro desde el JSON
            $cliente = $input['cliente'] ?? '';
            // Obtiene el código QR del filtro desde el JSON
            $qr      = $input['qr']      ?? '';
            // Obtiene el periodo del filtro desde el JSON
            $periodo = $input['periodo'] ?? 'TODOS';
            // Si el periodo viene vacío, se establece a 'TODOS'
            if (empty($periodo)) $periodo = 'TODOS';
            // Obtiene las imágenes enviadas desde el JSON
            $imagens = $input['imagenes'] ?? [];
        } else {
            // Modo GET (compatibilidad hacia atrás)
            // Obtiene el estado del filtro desde la URL
            $estado  = $_GET['estado']  ?? '';
            // Obtiene el cliente del filtro desde la URL
            $cliente = $_GET['cliente'] ?? '';
            // Obtiene el código QR del filtro desde la URL
            $qr      = $_GET['qr']      ?? '';
            // Obtiene el periodo del filtro desde la URL
            $periodo = $_GET['periodo'] ?? 'TODOS';
        }
        
        // Estadisticas FILTRADAS: el resumen ejecutivo del PDF debe reflejar
        // los mismos filtros que se ven en los graficos del dashboard
        // Obtiene las estadísticas administrativas con los filtros aplicados
        $stats = $this->model->getEstadisticasAdmin($rolEjecutor, $estado, $cliente, $qr, $periodo);

        // Genera el reporte ejecutivo en PDF con las estadísticas y filtros
        $this->pdf->generarReporteEjecutivo($stats, $estado, $periodo, $cliente, $qr, [], $imagens);
    }

    // Exportar a Excel (CSV con separador ; para Excel en español)
    public function exportarExcel(): void
    {
        try {
            // Obtiene el rol del ejecutor desde la sesión, por defecto 'ADMIN'
            $rolEjecutor = $_SESSION['rol_ejecutor'] ?? 'ADMIN';
            // Obtiene el filtro de estado desde la URL
            $estado  = $_GET['estado']  ?? '';
            // Obtiene el filtro de cliente desde la URL
            $cliente = $_GET['cliente'] ?? '';
            // Obtiene el filtro de código QR desde la URL
            $qr      = $_GET['qr']      ?? '';
            // Obtiene el periodo desde la URL (por defecto 'TODOS')
            $periodo = $_GET['periodo'] ?? 'TODOS';
            // Si el periodo viene vacío, se establece a 'TODOS'
            if (empty($periodo)) $periodo = 'TODOS';

            // Obtiene los alquileres filtrados (hasta 10000 registros)
            $alquileres = $this->model->getAlquileres($rolEjecutor, $estado, $cliente, $qr, 10000, 0, $periodo);

            // Crea el reporte Excel/CSV con los alquileres
            $this->excel->create($alquileres);

            // Genera y descarga el archivo CSV con nombre con fecha/hora
            $this->excel->Output('reporte_alquileres_' . date('Ymd_His') . '.csv', 'CSV');
        } catch (\Throwable $e) {
            // Registra el error en el log del servidor
            error_log('Error exportando Excel: ' . $e->getMessage());
            // Responde con código HTTP 500
            http_response_code(500);
            // Establece el encabezado de respuesta como JSON
            header('Content-Type: application/json');
            // Imprime un mensaje de error en JSON
            echo json_encode(['error' => 'Error generando el reporte Excel']);
        }
        // Finaliza la ejecución del script
        exit;
    }

    // Renderizar una vista
    private function render(string $vista, array $datos): void
    {
        // Extrae las variables del array de datos al ámbito local
        extract($datos);
        // Incluye el archivo de la vista correspondiente
        require __DIR__ . '/../Views/' . $vista . '.php';
    }
}
