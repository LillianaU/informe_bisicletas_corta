<?php
// ============================================================
// AUTOLOADER PSR-4 SIMPLE
// ============================================================

spl_autoload_register(function ($class) {
    // Prefijo del namespace
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';

    // ¿La clase usa el prefijo?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtener el nombre de clase relativo
    $relative_class = substr($class, $len);

    // Reemplazar separadores de namespace con separadores de directorio
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, cargarlo
    if (file_exists($file)) {
        require $file;
    }
});