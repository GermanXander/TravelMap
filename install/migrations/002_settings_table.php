<?php
/**
 * Migration 002: Settings Table
 *
 * Crea la tabla `settings` e inserta todos los valores por defecto.
 * Usa INSERT IGNORE para no sobreescribir valores ya personalizados.
 */
class Migration_002_settings_table
{
    public static function id(): string
    {
        return '002_settings_table';
    }

    public static function description(): string
    {
        return 'Tabla settings con configuraciones del sistema';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'settings'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key   VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type  ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
                description   VARCHAR(255),
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar todos los valores por defecto en un solo INSERT IGNORE
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('max_upload_size',            '8388608',                                     'number',  'Tamaño máximo de carga en bytes (8 MB por defecto)'),
            ('session_lifetime',           '86400',                                       'number',  'Tiempo de vida de la sesión en segundos (24 horas)'),
            ('timezone',                   'America/Argentina/Buenos_Aires',              'string',  'Zona horaria del sistema'),
            ('map_cluster_enabled',        'true',                                        'boolean', 'Habilitar clustering de puntos en el mapa público'),
            ('map_cluster_max_radius',     '30',                                          'number',  'Radio máximo del cluster en píxeles'),
            ('map_cluster_disable_at_zoom','15',                                          'number',  'Nivel de zoom donde se desactiva el clustering'),
            ('transport_color_plane',      '#FF4444',                                     'string',  'Color para rutas en avión'),
            ('transport_color_ship',       '#00AAAA',                                     'string',  'Color para rutas en barco'),
            ('transport_color_car',        '#4444FF',                                     'string',  'Color para rutas en auto'),
            ('transport_color_bike',       '#b88907',                                     'string',  'Color para rutas en motocicleta / bicicleta'),
            ('transport_color_train',      '#FF8800',                                     'string',  'Color para rutas en tren'),
            ('transport_color_walk',       '#44FF44',                                     'string',  'Color para rutas caminando'),
            ('transport_color_bus',        '#9C27B0',                                     'string',  'Color para rutas en autobús'),
            ('transport_color_aerial',     '#E91E63',                                     'string',  'Color para rutas aéreas (globo, teleférico, etc.)'),
            ('image_max_width',            '1920',                                        'number',  'Ancho máximo de imágenes en píxeles'),
            ('image_max_height',           '1080',                                        'number',  'Alto máximo de imágenes en píxeles'),
            ('image_quality',              '85',                                          'number',  'Calidad de compresión JPEG (0-100)'),
            ('thumbnail_max_width',        '400',                                         'number',  'Ancho máximo de miniaturas en píxeles'),
            ('thumbnail_max_height',       '300',                                         'number',  'Alto máximo de miniaturas en píxeles'),
            ('thumbnail_quality',          '80',                                          'number',  'Calidad JPEG para miniaturas (0-100)'),
            ('site_title',                 'Travel Map - Mis Viajes por el Mundo',        'string',  'Título del sitio público'),
            ('site_description',           'Explora mis viajes con mapas interactivos',   'string',  'Descripción del sitio para SEO'),
            ('site_favicon',               '',                                            'string',  'URL del favicon'),
            ('site_analytics_code',        '',                                            'string',  'Código de Google Analytics u otro script de análisis'),
            ('trip_tags_enabled',          'true',                                        'boolean', 'Habilitar sistema de etiquetas en los viajes'),
            ('distance_unit',             'km',                                           'string',  'Unidad de distancia (km o mi)'),
            ('default_language',           'en',                                          'string',  'Idioma por defecto del sitio (en, es, etc.)'),
            ('map_style',                  'voyager',                                     'string',  'Estilo del mapa base (positron, voyager, dark-matter, osm-liberty)')
        ");
    }
}
