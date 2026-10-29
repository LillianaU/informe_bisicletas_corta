<?php
// ============================================================
// CLASE DE CONEXION A LA BASE DE DATOS (PDO)
// ============================================================

namespace App\Database;

class Database
{
    private static ?\PDO $instancia = null;

    public static function obtenerInstancia(): \PDO
    {
        if (self::$instancia === null) {
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

            self::$instancia = new \PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return self::$instancia;
    }

    // Evitar clonación e instanciación directa
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new \Exception("No se puede deserializar singleton"); }
}