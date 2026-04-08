<?php
/**
 * Configuración Global del Proyecto
 *
 * INSTRUCCIONES:
 *   1. Copiá este archivo como config.php
 *   2. Editá los valores según tu entorno
 *   3. Nunca subas config.php a Git — ya está en .gitignore
 */

// Configuración de errores
// En producción usar: error_reporting(0); ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta raíz del proyecto
define('ROOT_PATH', dirname(__DIR__));

// Carpeta del proyecto en el servidor (cambiar según tu instalación)
// Ejemplos:
//   Local XAMPP:   '/TravelMap'
//   Producción:    ''   (si está en la raíz del dominio)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$folder   = '/TravelMap'; // <-- CAMBIAR AQUÍ

define('BASE_URL', $protocol . '://' . $host . $folder);

// Rutas de directorios importantes
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('SRC_PATH',     ROOT_PATH . '/src');

// URLs de recursos
define('ASSETS_URL',  BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// API Key de GraphHopper (obtener en https://www.graphhopper.com/)
// Plan gratuito: máximo 5 puntos por request
define('GRAPHHOPPER_API_KEY', 'YOUR_GRAPHHOPPER_API_KEY_HERE');

// Valores por defecto (se sobreescriben desde la BD si está disponible)
$defaultTimezone          = 'America/Argentina/Buenos_Aires';
$defaultMaxUploadSize     = 8 * 1024 * 1024; // 8MB
$defaultSessionLifetime   = 3600 * 24;        // 24 horas
$defaultImageMaxWidth     = 1920;
$defaultImageMaxHeight    = 1080;
$defaultImageQuality      = 85;
$defaultSiteTitle         = 'Travel Map - Mis Viajes por el Mundo';
$defaultSiteDescription   = 'Explora mis viajes por el mundo con mapas interactivos, rutas y fotografías';
$defaultSiteFavicon       = '';
$defaultSiteAnalyticsCode = '';

// Intentar cargar configuraciones dinámicas desde la base de datos
try {
    require_once CONFIG_PATH . '/db.php';
    require_once SRC_PATH . '/models/Settings.php';

    $conn          = getDB();
    $settingsModel = new Settings($conn);

    $timezone          = $settingsModel->get('timezone',          $defaultTimezone);
    $maxUploadSize     = $settingsModel->get('max_upload_size',   $defaultMaxUploadSize);
    $sessionLifetime   = $settingsModel->get('session_lifetime',  $defaultSessionLifetime);
    $imageMaxWidth     = $settingsModel->get('image_max_width',   $defaultImageMaxWidth);
    $imageMaxHeight    = $settingsModel->get('image_max_height',  $defaultImageMaxHeight);
    $imageQuality      = $settingsModel->get('image_quality',     $defaultImageQuality);
    $siteTitle         = $settingsModel->get('site_title',        $defaultSiteTitle);
    $siteDescription   = $settingsModel->get('site_description',  $defaultSiteDescription);
    $siteFavicon       = $settingsModel->get('site_favicon',      $defaultSiteFavicon);
    $siteAnalyticsCode = $settingsModel->get('site_analytics_code', $defaultSiteAnalyticsCode);
} catch (Exception $e) {
    error_log('Error al cargar configuraciones: ' . $e->getMessage());
    $timezone          = $defaultTimezone;
    $maxUploadSize     = $defaultMaxUploadSize;
    $sessionLifetime   = $defaultSessionLifetime;
    $imageMaxWidth     = $defaultImageMaxWidth;
    $imageMaxHeight    = $defaultImageMaxHeight;
    $imageQuality      = $defaultImageQuality;
    $siteTitle         = $defaultSiteTitle;
    $siteDescription   = $defaultSiteDescription;
    $siteFavicon       = $defaultSiteFavicon;
    $siteAnalyticsCode = $defaultSiteAnalyticsCode;
}

// Aplicar configuraciones
date_default_timezone_set($timezone);

define('MAX_UPLOAD_SIZE',          $maxUploadSize);
define('ALLOWED_IMAGE_TYPES',      ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png']);
define('IMAGE_MAX_WIDTH',          $imageMaxWidth);
define('IMAGE_MAX_HEIGHT',         $imageMaxHeight);
define('IMAGE_QUALITY',            $imageQuality);
define('SITE_TITLE',               $siteTitle);
define('SITE_DESCRIPTION',         $siteDescription);
define('SITE_FAVICON',             $siteFavicon);
define('SITE_ANALYTICS_CODE',      $siteAnalyticsCode);
define('SESSION_LIFETIME',         $sessionLifetime);

// Cargar sistema de internacionalización (i18n)
require_once SRC_PATH . '/helpers/Language.php';
$lang = Language::getInstance();
