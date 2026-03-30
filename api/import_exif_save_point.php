<?php
/**
 * API: Guardar punto de interés desde importación EXIF
 *
 * Mueve la imagen desde la carpeta temporal al directorio final,
 * crea el registro del punto de interés y devuelve el resultado.
 * Con action=cleanup elimina la carpeta temporal completa.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cuerpo JSON inválido']);
    exit;
}

// ---------------------------------------------------------------------------
// Helper: validate and sanitize a temp token / filename
// ---------------------------------------------------------------------------

function isValidToken(string $value): bool {
    return (bool) preg_match('/^[a-zA-Z0-9_]+$/', $value);
}

function isValidTempFilename(string $value): bool {
    return (bool) preg_match('/^[a-zA-Z0-9_.]+$/', $value) && strpos($value, '..') === false;
}

function deleteTempDir(string $tempToken): void {
    if (!isValidToken($tempToken)) {
        return;
    }
    $dir = ROOT_PATH . '/uploads/exif_temp/' . $tempToken;
    if (!is_dir($dir)) {
        return;
    }
    $files = glob($dir . '/*');
    if ($files) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// action=cleanup — delete the entire temp folder
// ---------------------------------------------------------------------------

if (isset($input['action']) && $input['action'] === 'cleanup') {
    $tokenFromSession = $_SESSION['exif_import_token'] ?? null;
    $tokenFromRequest = $input['temp_token'] ?? '';

    if ($tokenFromSession && isValidToken($tokenFromSession)) {
        deleteTempDir($tokenFromSession);
    }
    // Also try the one from request (in case session expired)
    if ($tokenFromRequest && isValidToken($tokenFromRequest) && $tokenFromRequest !== $tokenFromSession) {
        deleteTempDir($tokenFromRequest);
    }

    unset($_SESSION['exif_import_token']);
    echo json_encode(['success' => true]);
    exit;
}

// ---------------------------------------------------------------------------
// Save a single point
// ---------------------------------------------------------------------------

$requiredFields = ['trip_id', 'title', 'type', 'latitude', 'longitude', 'temp_token', 'temp_filename'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || (string)$input[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Campo requerido faltante: {$field}"]);
        exit;
    }
}

$tripId      = (int)$input['trip_id'];
$title       = trim((string)$input['title']);
$description = trim((string)($input['description'] ?? ''));
$type        = (string)$input['type'];
// Convertir fecha a formato DATETIME para la BD
$visitDate   = null;
if (!empty($input['visit_date'])) {
    $dateStr = (string)$input['visit_date'];
    
    // Caso 1: formato datetime-local desde HTML5 input (YYYY-MM-DDTHH:mm)
    if (strpos($dateStr, 'T') !== false) {
        $dateStr = str_replace('T', ' ', $dateStr);  // Reemplazar T por espacio
        // Asegurar que tenga segundos (MM:ss)
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $dateStr)) {
            $dateStr = $dateStr . ':00';  // Agregar :00 segundos
        }
        $visitDate = $dateStr;
    }
    // Caso 2: solo fecha (YYYY-MM-DD)
    elseif (strlen($dateStr) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        $visitDate = $dateStr . ' 12:00:00';
    }
    // Caso 3: ya viene con hora completa (YYYY-MM-DD HH:MM:SS)
    else {
        $visitDate = $dateStr;
    }
}
$latitude    = (float)$input['latitude'];
$longitude   = (float)$input['longitude'];
$tempToken   = (string)$input['temp_token'];
$tempFilename = (string)$input['temp_filename'];

// Validate type
$validTypes = array_keys(Point::getTypes());
if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de punto inválido']);
    exit;
}

// Validate coordinates
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordenadas fuera de rango']);
    exit;
}

// Validate trip_id
if ($tripId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de viaje inválido']);
    exit;
}

// Validate token format
if (!isValidToken($tempToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de token inválido']);
    exit;
}

// Validate token matches session
$sessionToken = $_SESSION['exif_import_token'] ?? null;
if (!$sessionToken || $sessionToken !== $tempToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de sesión inválido o expirado']);
    exit;
}

// Validate filename
if (!isValidTempFilename($tempFilename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}

// Validate date format if provided
if ($visitDate !== null) {
    // Intentar validar como DATETIME (Y-m-d H:i:s)
    $dtCheck = DateTime::createFromFormat('Y-m-d H:i:s', $visitDate);
    if (!$dtCheck || $dtCheck->format('Y-m-d H:i:s') !== $visitDate) {
        // Si no es datetime válido, intentar como solo fecha (Y-m-d)
        $dtCheck = DateTime::createFromFormat('Y-m-d', $visitDate);
        if (!$dtCheck || $dtCheck->format('Y-m-d') !== $visitDate) {
            $visitDate = null;  // Si ninguno coincide, descartar
        }
    }
}

// ---------------------------------------------------------------------------
// Move image from temp to final destination
// ---------------------------------------------------------------------------

$tempDir  = ROOT_PATH . '/uploads/exif_temp/' . $tempToken;
$tempFile = $tempDir . '/' . $tempFilename;

// Resolve the path and confirm it stays within the expected temp dir
$realTempDir  = realpath($tempDir);
$realTempFile = realpath($tempFile);

if (!$realTempDir || !$realTempFile || strpos($realTempFile, $realTempDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ruta de archivo inválida']);
    exit;
}

if (!file_exists($realTempFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Archivo temporal no encontrado']);
    exit;
}

$ext           = strtolower(pathinfo($tempFilename, PATHINFO_EXTENSION));
$finalFilename = preg_replace('/[^a-zA-Z0-9_]/', '_', uniqid('img_', true)) . '_' . time() . '.' . $ext;
$finalDir      = ROOT_PATH . '/uploads/points';
$finalPath     = $finalDir . '/' . $finalFilename;
$relativePath  = 'uploads/points/' . $finalFilename;

if (!is_dir($finalDir)) {
    mkdir($finalDir, 0755, true);
}

if (!copy($realTempFile, $finalPath)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo mover la imagen al destino final']);
    exit;
}
chmod($finalPath, 0644);

// Resize to configured max dimensions
try {
    $maxW = (int)$settingsModel->get('image_max_width', 1920);
    $maxH = (int)$settingsModel->get('image_max_height', 1080);
    $qual = (int)$settingsModel->get('image_quality', 85);
    FileHelper::resizeImage($finalPath, $finalPath, $maxW, $maxH, $qual);
} catch (Exception $e) {
    error_log('EXIF import: resize failed: ' . $e->getMessage());
}

// Create thumbnail
$thumbnailRelativePath = null;
try {
    $thumbDir = $finalDir . '/thumbs';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    $thumbPath = $thumbDir . '/' . $finalFilename;
    $thumbW    = (int)$settingsModel->get('thumbnail_max_width', 400);
    $thumbH    = (int)$settingsModel->get('thumbnail_max_height', 300);
    $thumbQ    = (int)$settingsModel->get('thumbnail_quality', 80);
    if (FileHelper::createThumbnail($finalPath, $thumbPath, $thumbW, $thumbH, $thumbQ)) {
        $thumbnailRelativePath = 'uploads/points/thumbs/' . $finalFilename;
    }
} catch (Exception $e) {
    error_log('EXIF import: thumbnail creation failed: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// Create point record
// ---------------------------------------------------------------------------

$pointModel = new Point();
$icon       = Point::getIconByType($type);

$pointId = $pointModel->create([
    'trip_id'     => $tripId,
    'title'       => $title,
    'description' => $description !== '' ? $description : null,
    'type'        => $type,
    'icon'        => $icon,
    'image_path'  => $relativePath,
    'latitude'    => $latitude,
    'longitude'   => $longitude,
    'visit_date'  => $visitDate,
]);

if (!$pointId) {
    @unlink($finalPath);
    if ($thumbnailRelativePath) {
        @unlink(ROOT_PATH . '/' . $thumbnailRelativePath);
    }
    echo json_encode(['success' => false, 'error' => 'No se pudo crear el registro del punto']);
    exit;
}

// Delete the temp file now that it has been successfully moved and saved
@unlink($realTempFile);

echo json_encode([
    'success'    => true,
    'point_id'   => (int)$pointId,
    'image_path' => $relativePath,
]);
