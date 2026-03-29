<?php
/**
 * Conexión a Base de Datos
 *
 * INSTRUCCIONES:
 *   1. Copiá este archivo como db.php
 *   2. Editá las credenciales según tu entorno
 *   3. Nunca subas db.php a Git — ya está en .gitignore
 */

class Database {
    private static $instance = null;
    private $connection;

    // ── Credenciales — CAMBIAR SEGÚN TU ENTORNO ──────────────────────────────
    private const DB_HOST    = '127.0.0.1'; // 'localhost' también válido
    private const DB_NAME    = 'travelmap';
    private const DB_USER    = 'root';
    private const DB_PASS    = '';          // contraseña de tu BD
    private const DB_CHARSET = 'utf8mb4';
    // ─────────────────────────────────────────────────────────────────────────

    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            die('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton.");
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}
