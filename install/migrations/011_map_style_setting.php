<?php
/**
 * Migration 011: Map Style Setting
 *
 * Agrega la configuración de estilo del mapa base.
 */
class Migration_011_map_style_setting
{
    public static function id(): string
    {
        return '011_map_style_setting';
    }

    public static function description(): string
    {
        return 'settings: agrega map_style';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = 'map_style'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('map_style', 'voyager', 'string', 'Estilo del mapa base (positron, voyager, dark-matter, osm-liberty)')
        ");
    }
}
