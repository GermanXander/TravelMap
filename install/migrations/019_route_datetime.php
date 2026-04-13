<?php
/**
 * Migration 019: Route Datetime
 *
 * Agrega campos de fecha y hora a la tabla routes:
 * - start_datetime: fecha y hora de inicio del trayecto
 * - end_datetime:   fecha y hora de fin del trayecto
 *                 (mismo esquema que points_of_interest.visit_date)
 */
class Migration_019_route_datetime
{
    public static function id(): string
    {
        return '019_route_datetime';
    }

    public static function description(): string
    {
        return 'Agregar start_datetime y end_datetime a la tabla routes';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'start_datetime'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE routes
                ADD COLUMN start_datetime DATETIME DEFAULT NULL AFTER image_path,
                ADD COLUMN end_datetime   DATETIME DEFAULT NULL AFTER start_datetime
        ");
    }
}
