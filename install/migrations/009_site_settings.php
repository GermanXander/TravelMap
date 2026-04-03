<?php
/**
 * Migration 009: Site Settings
 *
 * Agrega configuraciones del sitio público:
 * site_title, site_description, site_favicon, site_analytics_code.
 */
class Migration_009_site_settings
{
    public static function id(): string
    {
        return '009_site_settings';
    }

    public static function description(): string
    {
        return 'settings: agrega título, descripción, favicon y analytics del sitio';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = 'site_title'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('site_title',          'Travel Map - Mis Viajes por el Mundo',                   'string', 'Título del sitio público'),
            ('site_description',    'Explora mis viajes con mapas interactivos y fotografías', 'string', 'Descripción del sitio para SEO'),
            ('site_favicon',        '',                                                        'string', 'URL del favicon'),
            ('site_analytics_code', '',                                                        'string', 'Código de Google Analytics u otro script de análisis')
        ");
    }
}
