<?php
/**
 * Migration 004: Transport Types – Bus & Aerial
 *
 * Agrega 'bus' y 'aerial' al ENUM routes.transport_type
 * y crea los colores por defecto en settings.
 */
class Migration_004_transport_bus_aerial
{
    public static function id(): string
    {
        return '004_transport_bus_aerial';
    }

    public static function description(): string
    {
        return 'routes.transport_type: agrega bus y aerial';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'transport_type'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $col && strpos(strtolower($col['Type']), "'bus'") !== false;
    }

    public static function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'transport_type'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower($col['Type'] ?? '');

        if (strpos($type, "'bus'") === false) {
            $db->exec("
                ALTER TABLE routes
                MODIFY COLUMN transport_type
                    ENUM('plane','car','walk','ship','train','bus','aerial') NOT NULL
            ");
        }

        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('transport_color_bus',    '#9C27B0', 'string', 'Color para rutas en autobús'),
            ('transport_color_aerial', '#E91E63', 'string', 'Color para rutas aéreas (globo, teleférico)')
        ");
    }
}
