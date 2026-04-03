<?php
/**
 * Migration 013: Geocode Cache Table
 *
 * Crea la tabla geocode_cache para almacenar resultados de
 * geocodificación inversa (Nominatim) y reducir el rate limiting.
 */
class Migration_013_geocode_cache_table
{
    public static function id(): string
    {
        return '013_geocode_cache_table';
    }

    public static function description(): string
    {
        return 'Tabla geocode_cache para caché de geocodificación inversa';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'geocode_cache'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS geocode_cache (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                latitude     DECIMAL(10, 6) NOT NULL,
                longitude    DECIMAL(11, 6) NOT NULL,
                city         VARCHAR(255)   NOT NULL,
                display_name TEXT,
                country      VARCHAR(255),
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at   TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY   unique_coords (latitude, longitude),
                KEY          idx_expires   (expires_at),
                KEY          idx_created   (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
