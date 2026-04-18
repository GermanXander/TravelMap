<?php
/**
 * Migration 023: Add 'planned' status back to trips
 *
 * Adds 'planned' to the trips.status ENUM so trips can be:
 * - draft: hidden from the public map
 * - published: shown on the public map (past/current trips)
 * - planned: shown on the public map with visual distinction (future trips)
 */
class Migration_023_trip_status_planned
{
    public static function id(): string
    {
        return '023_trip_status_planned';
    }

    public static function description(): string
    {
        return 'trips.status: add planned status for future trips';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'status'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            return false;
        }
        $type = strtolower($col['Type']);
        return strpos($type, "'planned'") !== false;
    }

    public static function up(PDO $db): void
    {
        $db->exec("ALTER TABLE trips MODIFY status ENUM('draft','published','planned') DEFAULT 'draft'");
    }
}
