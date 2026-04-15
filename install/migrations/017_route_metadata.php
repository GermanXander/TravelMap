<?php
/**
 * Migration 017: Route Metadata
 *
 * Agrega columnas de metadatos a la tabla routes:
 * - name:        nombre descriptivo opcional del trayecto
 * - description: texto libre (equivalente al description de points_of_interest)
 * - image_path:  ruta relativa de la imagen subida al servidor
 *                (mismo esquema que points_of_interest.image_path)
 */
class Migration_017_route_metadata
{
    public static function id(): string
    {
        return '017_route_metadata';
    }

    public static function description(): string
    {
        return 'Agregar name, description e image_path a la tabla routes';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'name'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE routes
                ADD COLUMN name        VARCHAR(200) DEFAULT NULL AFTER color,
                ADD COLUMN description TEXT         DEFAULT NULL AFTER name,
                ADD COLUMN image_path  VARCHAR(255) DEFAULT NULL AFTER description
        ");
    }
}
