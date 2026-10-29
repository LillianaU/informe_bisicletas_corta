<?php
// ============================================================
// CONFIGURACION DE LA BASE DE DATOS
// ============================================================

// Devuelve un array asociativo con los parámetros de conexión a MySQL
return [
    // Host del servidor de base de datos (localhost en entorno de desarrollo)
    'host' => 'localhost',
    // Usuario de la base de datos
    'user' => 'root',
    // Contraseña del usuario (vacía en entorno local)
    'pass' => '',
    // Nombre de la base de datos a utilizar
    'dbname' => 'ebike_management',
    // Conjunto de caracteres para la conexión (utf8mb4 soporta emojis y caracteres especiales)
    'charset' => 'utf8mb4',
];
