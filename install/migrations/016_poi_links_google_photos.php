<?php
/**
 * Migration 016: Add google_photos to poi_links link_type ENUM
 *
 * Agrega 'google_photos' como tipo de enlace válido en poi_links.
 */
class Migration_016_poi_links_google_photos
{
    public static function id(): string
    {
        return '016_poi_links_google_photos';
    }

    public static function description(): string
    {
        return 'Agregar google_photos al ENUM link_type de poi_links';
    }

    public static function check(PDO $db): bool
    {
        if ((bool) $db->query("SHOW TABLES LIKE 'links'")->fetchColumn()) {
            return true;
        }
        $stmt = $db->query("SHOW COLUMNS FROM poi_links LIKE 'link_type'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return strpos($row['Type'], 'google_photos') !== false;
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE poi_links
            MODIFY COLUMN link_type ENUM(
                'website','google_maps','instagram','facebook',
                'twitter','tripadvisor','booking','airbnb',
                'youtube','wikipedia','google_photos','other'
            ) NOT NULL DEFAULT 'website'
        ");
    }
}
