<?php
// ============================================================
// FRONT CONTROLLER - PUNTO DE ENTRADA
// ============================================================

// Evitar que warnings/notices contaminen la salida (PDF, JSON)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

session_start();

// Configurar la ruta base del proyecto
define('BASE_PATH', dirname(__DIR__));

// Cargar autoloader PSR-4 propio
require BASE_PATH . '/autoload.php';

// Cargar Composer si está disponible
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

use App\Controllers\DashboardController;

$controller = new DashboardController();

// Definir la ruta a partir del parametro 'accion'
$accion = $_GET['accion'] ?? 'dashboard';

switch ($accion) {
    case 'dashboard':
        $controller->index();
        break;

    case 'api':
        // Ejemplo: ?accion=api&tipo=barras&estado=ACTIVO&periodo=MES
        $tipo = $_GET['tipo'] ?? '';
        $controller->apiChart($tipo);
        break;

    case 'exportar-pdf':
        $controller->exportarPdf();
        break;

    case 'exportar-excel':
        $controller->exportarExcel();
        break;

    default:
        http_response_code(404);
        echo 'Pagina no encontrada';
        break;
}
