<?php
/**
 * Migration 003: Trip Status ENUM
 *
 * Actualiza trips.status de ENUM('draft','public','planned')
 * a ENUM('draft','published').
 *
 * Mapeo: public → published, planned → published, draft → draft.
 */
class Migration_003_trip_status
{
    public static function id(): string
    {
        return '003_trip_status';
    }

    public static function description(): string
    {
        return 'trips.status: reemplaza public/planned por published';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'status'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            return false;
        }
        $type = strtolower($col['Type']);
        // Correcto si contiene 'published' y NO contiene 'public' (sin hed del 'published')
        return strpos($type, "'published'") !== false
            && strpos($type, "'public'")    === false
            && strpos($type, "'planned'")   === false;
    }

    public static function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'status'");
        $col  = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = strtolower($col['Type'] ?? '');

        // Si ya está correcto, salir
        if (strpos($type, "'published'") !== false && strpos($type, "'public'") === false) {
            return;
        }

        // 1. Columna temporal para el nuevo valor
        try {
            $db->exec("ALTER TABLE trips ADD COLUMN _mig_status VARCHAR(20)");
        } catch (PDOException $e) {
            // La columna ya existe de una ejecución parcial anterior – continuar
        }

        // 2. Mapear valores
        $db->exec("
            UPDATE trips SET _mig_status =
                CASE
                    WHEN status IN ('public', 'planned') THEN 'published'
                    ELSE 'draft'
                END
        ");

        // 3. Soltar restricción ENUM pasando a VARCHAR
        $db->exec("ALTER TABLE trips MODIFY status VARCHAR(20)");

        // 4. Copiar valor nuevo
        $db->exec("UPDATE trips SET status = _mig_status");

        // 5. Aplicar ENUM correcto
        $db->exec("ALTER TABLE trips MODIFY status ENUM('draft','published') DEFAULT 'draft'");

        // 6. Limpiar columna temporal
        $db->exec("ALTER TABLE trips DROP COLUMN _mig_status");
    }
}
