<?php
/**
 * Migration 015: POI visit_date → DATETIME
 *
 * Cambia points_of_interest.visit_date de DATE a DATETIME NULL DEFAULT NULL
 * para permitir registrar tanto la fecha como la hora de la visita.
 */
class Migration_015_poi_visit_date_datetime
{
    public static function id(): string
    {
        return '015_poi_visit_date_datetime';
    }

    public static function description(): string
    {
        return 'points_of_interest.visit_date: cambia tipo DATE → DATETIME';
    }

    /**
     * Devuelve true si el campo ya es DATETIME (migración ya aplicada).
     */
    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM `points_of_interest` LIKE 'visit_date'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            return false;
        }
        return strtolower($col['Type']) === 'datetime';
    }

    public static function up(PDO $db): void
    {
        // Solo actuar si el campo es DATE; si ya es DATETIME, nada que hacer.
        $stmt = $db->query("SHOW COLUMNS FROM `points_of_interest` LIKE 'visit_date'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col || strtolower($col['Type']) === 'datetime') {
            return;
        }

        $db->exec("
            ALTER TABLE `points_of_interest`
            CHANGE `visit_date` `visit_date` DATETIME NULL DEFAULT NULL
        ");
    }
}
