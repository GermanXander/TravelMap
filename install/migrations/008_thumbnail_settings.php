<?php
/**
 * Migration 008: Thumbnail Settings
 *
 * Agrega la configuración de generación de miniaturas:
 * thumbnail_max_width, thumbnail_max_height, thumbnail_quality.
 */
class Migration_008_thumbnail_settings
{
    public static function id(): string
    {
        return '008_thumbnail_settings';
    }

    public static function description(): string
    {
        return 'settings: agrega parámetros de miniaturas (thumbnails)';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = 'thumbnail_max_width'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('thumbnail_max_width',  '400', 'number', 'Ancho máximo de miniaturas en píxeles'),
            ('thumbnail_max_height', '300', 'number', 'Alto máximo de miniaturas en píxeles'),
            ('thumbnail_quality',    '80',  'number', 'Calidad JPEG para miniaturas (0-100)')
        ");
    }
}
