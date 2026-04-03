<?php
/**
 * Migration 007: Image Settings
 *
 * Agrega la configuración de procesamiento de imágenes:
 * image_max_width, image_max_height, image_quality.
 */
class Migration_007_image_settings
{
    public static function id(): string
    {
        return '007_image_settings';
    }

    public static function description(): string
    {
        return 'settings: agrega parámetros de procesamiento de imágenes';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = 'image_max_width'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('image_max_width',  '1920', 'number', 'Ancho máximo de imágenes en píxeles'),
            ('image_max_height', '1080', 'number', 'Alto máximo de imágenes en píxeles'),
            ('image_quality',    '85',   'number', 'Calidad de compresión JPEG (0-100)')
        ");
    }
}
