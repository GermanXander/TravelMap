<?php
/**
 * Migration 022: Password Shares Table
 *
 * Agrega tabla para compartir viajes con contraseñas temporales.
 */
class Migration_022_password_shares
{
    public static function id(): string
    {
        return '022_password_shares';
    }

    public static function description(): string
    {
        return 'Tabla password_shares para compartir viajes con contraseñas';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'password_shares'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE password_shares (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                password    VARCHAR(255) NOT NULL UNIQUE,
                trips       VARCHAR(1000) NOT NULL COMMENT 'Lista de IDs de viajes separados por coma, o * para todos',
                description VARCHAR(100) NULL DEFAULT NULL COMMENT 'Descripción opcional de uso de la contraseña',
                created_at  DATE NOT NULL DEFAULT (CURRENT_DATE),
                expires_at  DATE NULL DEFAULT NULL,
                active      BOOLEAN NOT NULL DEFAULT TRUE,
                INDEX idx_expires_at (expires_at),
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}