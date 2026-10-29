<?php
// ============================================================
// CLASE DE CONEXION A LA BASE DE DATOS (PDO)
// ============================================================

// Declara el espacio de nombres para la clase de base de datos
namespace App\Database;

// Clase Database implementa el patrón Singleton para la conexión PDO
class Database
{
    // Propiedad estática que almacena la única instancia de PDO
    private static ?\PDO $instancia = null;

    // Método estático para obtener la instancia única de PDO
    public static function obtenerInstancia(): \PDO
    {
        // Si aún no se ha creado la instancia, la crea
        if (self::$instancia === null) {
            // Carga la configuración de base de datos desde el archivo de config
            $config = require __DIR__ . '/../../config/database.php';

            // Construye el DSN (Data Source Name) para la conexión MySQL
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

            // Crea la instancia de PDO con las credenciales y opciones
            self::$instancia = new \PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    // Lanza excepciones en caso de error de base de datos
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    // Modo de obtención por defecto: array asociativo
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    // Desactiva la emulación de prepared statements (mejor seguridad)
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        // Devuelve la instancia de PDO (existente o recién creada)
        return self::$instancia;
    }

    // Evitar clonación e instanciación directa
    // Constructor privado para impedir instanciación directa (Singleton)
    private function __construct() {}
    // Clonación privada para evitar duplicar la instancia
    private function __clone() {}
    // Al intentar deserializar, lanza excepción para proteger el Singleton
    public function __wakeup() { throw new \Exception("No se puede deserializar singleton"); }
}
