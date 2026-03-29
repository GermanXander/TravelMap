<?php
/**
 * API: Importar imágenes con datos EXIF
 *
 * Recibe imágenes JPEG, las guarda en una carpeta temporal,
 * extrae sus datos EXIF (GPS + fecha) y devuelve el resultado.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

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

// ---------------------------------------------------------------------------
// Helpers EXIF
// ---------------------------------------------------------------------------

function exifRationalToFloat(string $rational): float {
    if (strpos($rational, '/') !== false) {
        [$num, $den] = explode('/', $rational, 2);
        return (float)$den != 0 ? (float)$num / (float)$den : 0.0;
    }
    return (float)$rational;
}

function exifGpsToDecimal(array $coords, string $hemisphere): ?float {
    if (count($coords) < 3) {
        return null;
    }
    $degrees = exifRationalToFloat($coords[0]);
    $minutes = exifRationalToFloat($coords[1]);
    $seconds = exifRationalToFloat($coords[2]);
    $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);
    if (in_array(strtoupper(trim($hemisphere)), ['S', 'W'], true)) {
        $decimal = -$decimal;
    }
    return round($decimal, 7);
}

function readImageExifData(string $filePath): array {
    $result = [
        'has_gps'   => false,
        'has_date'  => false,
        'latitude'  => null,
        'longitude' => null,
        'date'      => null,
        'timestamp' => null,
    ];

    if (!function_exists('exif_read_data')) {
        return $result;
    }

    $exif = @exif_read_data($filePath, 0, true);
    if (!$exif) {
        return $result;
    }

    // --- Date ---
    $dateStr = $exif['EXIF']['DateTimeOriginal']
        ?? $exif['EXIF']['DateTimeDigitized']
        ?? $exif['IFD0']['DateTime']
        ?? null;

    if ($dateStr) {
        $dt = DateTime::createFromFormat('Y:m:d H:i:s', trim($dateStr));
        if ($dt) {
            $result['has_date']  = true;
            $result['date']      = $dt->format('Y-m-d\TH:i');
            $result['timestamp'] = $dt->getTimestamp();
        }
    }

    // --- GPS ---
    if (!empty($exif['GPS'])) {
        $gps = $exif['GPS'];
        if (
            !empty($gps['GPSLatitude']) && is_array($gps['GPSLatitude']) &&
            !empty($gps['GPSLongitude']) && is_array($gps['GPSLongitude']) &&
            !empty($gps['GPSLatitudeRef']) &&
            !empty($gps['GPSLongitudeRef'])
        ) {
            $lat = exifGpsToDecimal($gps['GPSLatitude'],  $gps['GPSLatitudeRef']);
            $lng = exifGpsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
            if ($lat !== null && $lng !== null && ($lat != 0 || $lng != 0)) {
                $result['has_gps']   = true;
                $result['latitude']  = $lat;
                $result['longitude'] = $lng;
            }
        }
    }

    return $result;
}

// ---------------------------------------------------------------------------
// Validate files
// ---------------------------------------------------------------------------

if (empty($_FILES['images'])) {
    echo json_encode(['success' => false, 'error' => 'No se enviaron imágenes']);
    exit;
}

// Normalize the multiple-file $_FILES array
$rawFiles = $_FILES['images'];
$files = [];

if (is_array($rawFiles['name'])) {
    $count = count($rawFiles['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name'     => $rawFiles['name'][$i],
            'type'     => $rawFiles['type'][$i],
            'tmp_name' => $rawFiles['tmp_name'][$i],
            'error'    => $rawFiles['error'][$i],
            'size'     => $rawFiles['size'][$i],
        ];
    }
} else {
    $files[] = $rawFiles;
}

// ---------------------------------------------------------------------------
// Create temp folder
// ---------------------------------------------------------------------------

// Generate a clean alphanumeric token
$rawToken = uniqid('exif_', true);
$token     = preg_replace('/[^a-zA-Z0-9_]/', '_', $rawToken);
$_SESSION['exif_import_token'] = $token;

$tempBaseDir = ROOT_PATH . '/uploads/exif_temp';
$tempDir     = $tempBaseDir . '/' . $token;

if (!is_dir($tempBaseDir)) {
    mkdir($tempBaseDir, 0750, true);
}
if (!mkdir($tempDir, 0750, true)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo crear la carpeta temporal']);
    exit;
}

// Add an .htaccess in temp base to prevent PHP execution
$htaccess = $tempBaseDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb\n<FilesMatch \"\\.php$\">\n    Deny from all\n</FilesMatch>\n");
}

// ---------------------------------------------------------------------------
// Process each file
// ---------------------------------------------------------------------------

$allowedMimes = ['image/jpeg'];
$allowedExts  = ['jpg', 'jpeg'];
$images       = [];
$errors       = [];

foreach ($files as $file) {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . ': error de subida (' . (int)$file['error'] . ')';
        continue;
    }

    // Size check
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $maxMb = round(MAX_UPLOAD_SIZE / 1024 / 1024, 1);
        $errors[] = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . ": excede el tamaño máximo ({$maxMb} MB)";
        continue;
    }

    // MIME check (real, not browser-provided)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeReal, $allowedMimes, true)) {
        $errors[] = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . ': solo se permiten imágenes JPEG (requerido para datos EXIF)';
        continue;
    }

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $errors[] = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . ': extensión no permitida';
        continue;
    }

    // Save to temp
    $tempFilename = preg_replace('/[^a-zA-Z0-9_]/', '_', uniqid('tmp_', true)) . '.' . $ext;
    $tempPath     = $tempDir . '/' . $tempFilename;

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        $errors[] = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') . ': no se pudo guardar';
        continue;
    }

    chmod($tempPath, 0644);

    // Read EXIF
    $exifData = readImageExifData($tempPath);

    $images[] = [
        'temp_filename' => $tempFilename,
        'original_name' => htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
        'url'           => UPLOADS_URL . '/exif_temp/' . $token . '/' . $tempFilename,
        'has_gps'       => $exifData['has_gps'],
        'has_date'      => $exifData['has_date'],
        'latitude'      => $exifData['latitude'],
        'longitude'     => $exifData['longitude'],
        'date'          => $exifData['date'],
        'timestamp'     => $exifData['timestamp'],
    ];
}

// Sort by EXIF timestamp (null values go to the end)
usort($images, function (array $a, array $b): int {
    $ta = $a['timestamp'] ?? PHP_INT_MAX;
    $tb = $b['timestamp'] ?? PHP_INT_MAX;
    return $ta <=> $tb;
});

echo json_encode([
    'success' => true,
    'token'   => $token,
    'count'   => count($images),
    'images'  => $images,
    'errors'  => $errors,
]);
