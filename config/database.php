<?php
// ============================================================
// CONFIGURACION DE LA BASE DE DATOS
// ============================================================
// Lee variables de entorno (útil para Docker) con valores por defecto
// para desarrollo local (Laragon/XAMPP).

return [
    'host'    => getenv('DB_HOST')    ?: 'localhost',
    'user'    => getenv('DB_USER')    ?: 'root',
    'pass'    => getenv('DB_PASS')    ?: '',
    'dbname'  => getenv('DB_NAME')    ?: 'ebike_management',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
