<?php
/**
 * Migration 014: POI Links Table
 *
 * Crea la tabla poi_links para enlaces externos tipificados
 * asociados a puntos de interés (website, redes sociales, maps, etc.).
 */
class Migration_014_poi_links_table
{
    public static function id(): string
    {
        return '014_poi_links_table';
    }

    public static function description(): string
    {
        return 'Tabla poi_links: enlaces externos para puntos de interés';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'poi_links'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS poi_links (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poi_id     INT UNSIGNED NOT NULL,
                link_type  ENUM(
                               'website', 'google_maps', 'instagram', 'facebook',
                               'twitter', 'tripadvisor', 'booking', 'airbnb',
                               'youtube', 'wikipedia', 'other'
                           ) NOT NULL DEFAULT 'website',
                url        VARCHAR(500) NOT NULL,
                label      VARCHAR(100) DEFAULT NULL,
                sort_order TINYINT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
                INDEX idx_poi_id (poi_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
