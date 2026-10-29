<?php
// ============================================================
// AUTOLOADER PSR-4 SIMPLE
// ============================================================

// Registra una función anónima como autoloader en el registro de autoloaders de PHP
spl_autoload_register(function ($class) {
    // Prefijo del namespace que este autoloader se encarga de cargar
    $prefix = 'App\\';
    // Directorio base donde residen las clases del namespace App
    $base_dir = __DIR__ . '/src/';

    // Longitud del prefijo, necesaria para comparar con el nombre de clase solicitado
    $len = strlen($prefix);
    // Si el nombre de la clase no comienza con el prefijo App\, este autoloader no la maneja
    if (strncmp($prefix, $class, $len) !== 0) {
        // Retorna sin hacer nada para que otros autoloaders (p. ej. Composer) puedan intentarlo
        return;
    }

    // Obtiene el nombre de la clase sin el prefijo del namespace
    $relative_class = substr($class, $len);

    // Reemplaza los separadores de namespace (\) por separadores de directorio (/) y añade la extensión .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Verifica que el archivo exista antes de incluirlo para evitar errores fatales
    if (file_exists($file)) {
        // Incluye (require) el archivo de la clase solicitada
        require $file;
    }
});
