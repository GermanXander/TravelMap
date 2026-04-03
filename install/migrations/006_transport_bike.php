<?php
/**
 * Migration 006: Transport Type – Bike
 *
 * Agrega 'bike' al ENUM routes.transport_type
 * y crea el color por defecto en settings.
 */
class Migration_006_transport_bike
{
    public static function id(): string
    {
        return '006_transport_bike';
    }

    public static function description(): string
    {
        return 'routes.transport_type: agrega bike';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'transport_type'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $col && strpos(strtolower($col['Type']), "'bike'") !== false;
    }

    public static function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'transport_type'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower($col['Type'] ?? '');

        if (strpos($type, "'bike'") === false) {
            // Determinar si bus/aerial ya existen para componer el ENUM correcto
            $hasBus    = strpos($type, "'bus'")    !== false;
            $hasAerial = strpos($type, "'aerial'") !== false;

            if ($hasBus && $hasAerial) {
                $db->exec("
                    ALTER TABLE routes
                    MODIFY COLUMN transport_type
                        ENUM('plane','car','walk','ship','train','bike','bus','aerial') NOT NULL
                ");
            } else {
                $db->exec("
                    ALTER TABLE routes
                    MODIFY COLUMN transport_type
                        ENUM('plane','car','walk','ship','train','bike') NOT NULL
                ");
            }
        }

        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('transport_color_bike', '#b88907', 'string', 'Color para rutas en bicicleta / motocicleta')
        ");
    }
}
