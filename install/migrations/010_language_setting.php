<?php
/**
 * Migration 010: Language Setting
 *
 * Agrega la configuración de idioma por defecto del sitio.
 */
class Migration_010_language_setting
{
    public static function id(): string
    {
        return '010_language_setting';
    }

    public static function description(): string
    {
        return 'settings: agrega default_language';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = 'default_language'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('default_language', 'en', 'string', 'Idioma por defecto del sitio (en, es, etc.)')
        ");
    }
}
