<?php
/**
 * Migration 005: Route Distance
 *
 * Agrega las columnas is_round_trip y distance_meters a routes,
 * y el setting distance_unit.
 */
class Migration_005_route_distance
{
    public static function id(): string
    {
        return '005_route_distance';
    }

    public static function description(): string
    {
        return 'routes: agrega is_round_trip y distance_meters';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'distance_meters'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        // Agregar is_round_trip si no existe
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'is_round_trip'");
        if (!$stmt->fetchColumn()) {
            $db->exec("
                ALTER TABLE routes
                ADD COLUMN is_round_trip TINYINT(1) DEFAULT 0 AFTER geojson_data
            ");
        }

        // Agregar distance_meters si no existe
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'distance_meters'");
        if (!$stmt->fetchColumn()) {
            $db->exec("
                ALTER TABLE routes
                ADD COLUMN distance_meters INT UNSIGNED DEFAULT 0 AFTER is_round_trip
            ");
        }

        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('distance_unit', 'km', 'string', 'Unidad de distancia (km o mi)')
        ");
    }
}
