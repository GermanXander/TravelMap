<?php
/**
 * Migration 018: Route Links Table
 *
 * Crea la tabla route_links para enlaces externos tipificados
 * asociados a trayectos (routes). Es la contraparte exacta de
 * poi_links para puntos de interés: mismos tipos de enlace,
 * misma estructura, FK apuntando a routes(id).
 */
class Migration_018_route_links_table
{
    public static function id(): string
    {
        return '018_route_links_table';
    }

    public static function description(): string
    {
        return 'Tabla route_links: enlaces externos para trayectos';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'route_links'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS route_links (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                route_id   INT UNSIGNED NOT NULL,
                link_type  ENUM(
                               'website', 'google_maps', 'instagram', 'facebook',
                               'twitter', 'tripadvisor', 'booking', 'airbnb',
                               'youtube', 'wikipedia', 'google_photos', 'other'
                           ) NOT NULL DEFAULT 'website',
                url        VARCHAR(500) NOT NULL,
                label      VARCHAR(100) DEFAULT NULL,
                sort_order TINYINT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
                INDEX idx_route_id (route_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
