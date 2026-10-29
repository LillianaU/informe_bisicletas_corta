<?php
// ============================================================
// FRONT CONTROLLER - PUNTO DE ENTRADA
// ============================================================

// Evitar que warnings/notices contaminen la salida (PDF, JSON)
// Desactiva la notificación de warnings, notices y deprecaciones en el reporte de errores
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
// Evita que los errores se muestren en la salida (útil para respuestas JSON/PDF)
ini_set('display_errors', '0');

// Inicia o reanuda la sesión PHP para acceder a $_SESSION
session_start();

// Configurar la ruta base del proyecto (directorio padre de public)
define('BASE_PATH', dirname(__DIR__));

// Cargar autoloader PSR-4 propio
// Incluye el autoloader personalizado del proyecto
require BASE_PATH . '/autoload.php';

// Cargar Composer si está disponible
// Verifica si existe el autoloader de Composer y lo carga si está presente
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    // Incluye el autoloader generado por Composer
    require BASE_PATH . '/vendor/autoload.php';
}

// Importa la clase DashboardController al espacio de nombres actual
use App\Controllers\DashboardController;

// Instancia el controlador principal del dashboard
$controller = new DashboardController();

// Definir la ruta a partir del parametro 'accion'
// Obtiene la acción solicitada desde la URL; por defecto 'dashboard'
$accion = $_GET['accion'] ?? 'dashboard';

// Estructura de control que despacha la acción solicitada
switch ($accion) {
    case 'dashboard':
        // Muestra la página principal del dashboard
        $controller->index();
        break;

    case 'api':
        // Ejemplo: ?accion=api&tipo=barras&estado=ACTIVO&periodo=MES
        // Obtiene el tipo de gráfico/datos a retornar
        $tipo = $_GET['tipo'] ?? '';
        // Llama al método que responde JSON para los gráficos
        $controller->apiChart($tipo);
        break;

    case 'exportar-pdf':
        // Genera y descarga el reporte en PDF
        $controller->exportarPdf();
        break;

    case 'exportar-excel':
        // Genera y descarga el reporte en Excel/CSV
        $controller->exportarExcel();
        break;

    default:
        // Acción no reconocida: responde con código HTTP 404
        http_response_code(404);
        // Mensaje de página no encontrada
        echo 'Pagina no encontrada';
        break;
}
