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

function readImageExifData(string $filePath, string $originalFilename = ''): array {
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
        $exif = [];
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

    // --- Fallback: Extract date from filename if EXIF data is missing or suspicious ---
    // Try fallback if: no date extracted yet OR the filename looks like it has date info
    // Use original filename if provided, otherwise use temporary filename
    $filename = !empty($originalFilename) ? $originalFilename : basename($filePath);
    if (!$result['has_date'] || preg_match('/(\d{4})(\d{2})(\d{2})[_-]?(\d{2})(\d{2})(\d{2})/', $filename)) {
        $matched = false;
        $debugLog = [];

        // Pattern 1: IMG_20250329_143025 or IMG-20250329-143025 (with separators)
        if (!$matched && preg_match('/(\d{4})(\d{2})(\d{2})[_-](\d{2})(\d{2})(\d{2})/', $filename, $matches)) {
            $debugLog[] = "Pattern 1 matched";
            $year   = $matches[1];
            $month  = $matches[2];
            $day    = $matches[3];
            $hour   = $matches[4];
            $minute = $matches[5];
            $second = $matches[6];
            $matched = true;
        }

        // Pattern 2: IMG20250329143025 (no separators)
        if (!$matched && preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $filename, $matches)) {
            $debugLog[] = "Pattern 2 matched";
            $year   = $matches[1];
            $month  = $matches[2];
            $day    = $matches[3];
            $hour   = $matches[4];
            $minute = $matches[5];
            $second = $matches[6];
            $matched = true;
        }

        // Pattern 3: 2025-03-29_14-30-25 or 2025_03_29_14_30_25 (fully formatted)
        if (!$matched && preg_match('/(\d{4})[-_](\d{2})[-_](\d{2})[-_T](\d{2})[-_:](\d{2})[-_:](\d{2})/', $filename, $matches)) {
            $debugLog[] = "Pattern 3 matched";
            $year   = $matches[1];
            $month  = $matches[2];
            $day    = $matches[3];
            $hour   = $matches[4];
            $minute = $matches[5];
            $second = $matches[6];
            $matched = true;
        }

        // Validate and save
        if ($matched) {
            // Validate month, day, hour, minute, second are in valid ranges
            $monthInt = (int)$month;
            $dayInt   = (int)$day;
            $hourInt  = (int)$hour;
            $minInt   = (int)$minute;
            $secInt   = (int)$second;
            
            if ($monthInt >= 1 && $monthInt <= 12 && 
                $dayInt >= 1 && $dayInt <= 31 && 
                $hourInt >= 0 && $hourInt <= 23 && 
                $minInt >= 0 && $minInt <= 59 && 
                $secInt >= 0 && $secInt <= 59) {
                
                $dateTimeStr = "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeStr);
                if ($dt && $dt->format('Y-m-d H:i:s') === $dateTimeStr) {
                    $result['has_date']  = true;
                    $result['date']      = $dt->format('Y-m-d\TH:i');
                    $result['timestamp'] = $dt->getTimestamp();
                    error_log("EXIF Fallback SUCCESS for " . $filename . ": " . $result['date']);
                } else {
                    error_log("EXIF Fallback FAILED DateTime validation for " . $filename . ": dateTimeStr=" . $dateTimeStr);
                }
            } else {
                error_log("EXIF Fallback FAILED range validation for " . $filename . ": month={$monthInt}, day={$dayInt}, hour={$hourInt}, min={$minInt}, sec={$secInt}");
            }
        } else {
            error_log("EXIF Fallback NO PATTERN MATCH for " . $filename);
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

    // Read EXIF (pass original filename for fallback date extraction)
    $exifData = readImageExifData($tempPath, $file['name']);

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
