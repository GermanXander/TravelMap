<?php
/**
 * Migration 001: Initial Schema
 *
 * Crea las 4 tablas base del sistema:
 *   users, trips, routes, points_of_interest
 *
 * Las tablas se crean en su forma INICIAL (antes de las migraciones posteriores).
 * Las migraciones 003–006 aplicarán los cambios necesarios a instalaciones antiguas.
 * En instalaciones nuevas, esta migración no se ejecuta: se usa database.sql directamente.
 */
class Migration_001_initial_schema
{
    public static function id(): string
    {
        return '001_initial_schema';
    }

    public static function description(): string
    {
        return 'Tablas base: users, trips, routes, points_of_interest';
    }

    /**
     * Devuelve true si las 4 tablas base ya existen.
     */
    public static function check(PDO $db): bool
    {
        foreach (['users', 'trips', 'routes', 'points_of_interest'] as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetchColumn()) {
                return false;
            }
        }
        return true;
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(50)  NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Status inicial con valores legacy (migración 003 lo actualizará)
        $db->exec("
            CREATE TABLE IF NOT EXISTS trips (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title       VARCHAR(200) NOT NULL,
                description TEXT,
                start_date  DATE,
                end_date    DATE,
                color_hex   VARCHAR(7) DEFAULT '#3388ff',
                status      ENUM('draft', 'public', 'planned') DEFAULT 'draft',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ENUM inicial sin bus/aerial/bike; sin is_round_trip/distance_meters
        $db->exec("
            CREATE TABLE IF NOT EXISTS routes (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trip_id        INT UNSIGNED NOT NULL,
                transport_type ENUM('plane', 'car', 'walk', 'ship', 'train') NOT NULL,
                geojson_data   LONGTEXT NOT NULL,
                color          VARCHAR(7) DEFAULT '#3388ff',
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
                INDEX idx_trip_id (trip_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS points_of_interest (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trip_id     INT UNSIGNED NOT NULL,
                title       VARCHAR(200) NOT NULL,
                description TEXT,
                type        ENUM('stay', 'visit', 'food', 'waypoint') NOT NULL,
                icon        VARCHAR(100) DEFAULT 'default',
                image_path  VARCHAR(255),
                latitude    DECIMAL(10, 8) NOT NULL,
                longitude   DECIMAL(11, 8) NOT NULL,
                visit_date  DATETIME,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
                INDEX idx_trip_id (trip_id),
                INDEX idx_type (type),
                INDEX idx_coordinates (latitude, longitude)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
