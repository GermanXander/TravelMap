<?php
/**
 * Migration 012: Trip Tags Table
 *
 * Crea la tabla trip_tags para etiquetas de viajes
 * y agrega el setting trip_tags_enabled.
 */
class Migration_012_trip_tags_table
{
    public static function id(): string
    {
        return '012_trip_tags_table';
    }

    public static function description(): string
    {
        return 'Tabla trip_tags y setting trip_tags_enabled';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'trip_tags'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS trip_tags (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trip_id    INT UNSIGNED NOT NULL,
                tag_name   VARCHAR(50)  NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
                INDEX      idx_trip_tag (trip_id),
                UNIQUE KEY unique_trip_tag (trip_id, tag_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('trip_tags_enabled', 'true', 'boolean', 'Habilitar sistema de etiquetas en los viajes')
        ");
    }
}
