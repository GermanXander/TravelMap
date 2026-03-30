<?php
/**
 * API: Geocodificación inversa
 *
 * Convierte coordenadas (lat, lng) en un nombre de ciudad/lugar
 * usando el servicio gratuito Nominatim de OpenStreetMap.
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Validate coordinates
if (!isset($_GET['lat'], $_GET['lng'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros lat y lng requeridos']);
    exit;
}

$lat = filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'], FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordenadas inválidas']);
    exit;
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Coordenadas fuera de rango']);
    exit;
}

// Buscar en cache primero
$db = getDB();
try {
    $cacheStmt = $db->prepare('
        SELECT `city`, `display_name`, `country` FROM geocode_cache 
        WHERE ABS(latitude - ?) < 0.000001 AND ABS(longitude - ?) < 0.000001
        LIMIT 1
    ');
    $cacheStmt->execute([$lat, $lng]);
    $cached = $cacheStmt->fetch();
    
    if ($cached) {
        error_log("[reverse_geocode] CACHE HIT for lat=$lat, lng=$lng");
        echo json_encode([
            'success'      => true,
            'city'         => $cached['city'],
            'display_name' => $cached['display_name'] ?? $cached['city'],
            'country'      => $cached['country'],
            'source'       => 'cache',
            'timestamp'    => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("[reverse_geocode] Cache lookup failed: " . $e->getMessage());
    // Continue to Nominatim if cache fails
}

// Rate limit: máximo 1 request/segundo
$lastRequestFile = sys_get_temp_dir() . '/nominatim_last_request.txt';
$lastRequest = @file_get_contents($lastRequestFile);
if ($lastRequest) {
    $elapsed = microtime(true) - (float)$lastRequest;
    if ($elapsed < 1.0) {
        usleep((1.0 - $elapsed) * 1000000);
    }
}
file_put_contents($lastRequestFile, microtime(true));

// Build Nominatim reverse geocode URL (zoom=10 = city level)
$nominatimUrl = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'lat'            => $lat,
    'lon'            => $lng,
    'format'         => 'json',
    'addressdetails' => 1,
    'zoom'           => 10,
]);

// Execute cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,            $nominatimUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_USERAGENT,      'TravelMap/1.0 (PHP Proxy) Contact: admin@travelmap.dominio.net');
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Accept: application/json', 'Accept-Language: es,en']);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrNo = curl_errno($ch);
curl_close($ch);

// Log request details for debugging
error_log("[reverse_geocode] Request to Nominatim: $nominatimUrl");
error_log("[reverse_geocode] HTTP Code: $httpCode, cURL Error: $curlError ($curlErrNo)");

if ($response === false) {
    error_log("[reverse_geocode] CURL request failed: $curlError");
    echo json_encode([
        'success' => false,
        'error'   => 'Error al conectar con el servicio de geocodificación',
        'details' => "cURL Error: $curlError (Code: $curlErrNo)",
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if ($httpCode !== 200) {
    error_log("[reverse_geocode] HTTP Error $httpCode. Response: " . substr($response, 0, 500));
    
    // Try to parse as JSON error
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error'] ?? trim($response);
    
    echo json_encode([
        'success' => false,
        'error'   => "Servidor Nominatim respondió con código $httpCode",
        'details' => $errorMsg ?: 'Sin información adicional',
        'http_code' => $httpCode,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

$data = json_decode($response, true);
if ($data === null) {
    error_log("[reverse_geocode] JSON decode failed. Response: " . substr($response, 0, 500));
    echo json_encode([
        'success' => false,
        'error'   => 'Respuesta inválida del servidor de geocodificación',
        'details' => 'No se pudo procesar la respuesta JSON',
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if (isset($data['error'])) {
    error_log("[reverse_geocode] Nominatim error: " . $data['error']);
    echo json_encode([
        'success' => false,
        'error'   => 'Nominatim no encontró la ubicación',
        'details' => $data['error'],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if (!$data) {
    error_log("[reverse_geocode] Empty response from Nominatim");
    echo json_encode(['success' => false, 'error' => 'Ubicación no encontrada', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

// Extract the most relevant city-level name from the address components
$address = $data['address'] ?? [];

$city = $address['city']
    ?? $address['town']
    ?? $address['village']
    ?? $address['municipality']
    ?? $address['county']
    ?? $address['state']
    ?? null;

if (!$city) {
    error_log("[reverse_geocode] No city found. Address components: " . json_encode($address));
    echo json_encode([
        'success' => false,
        'error' => 'No se encontró nombre de ciudad para estas coordenadas',
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

error_log("[reverse_geocode] SUCCESS: Found city '$city' for lat=$lat, lng=$lng");

// Guardar en cache
try {
    $cacheInsert = $db->prepare('
        INSERT INTO geocode_cache (latitude, longitude, city, display_name, country)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            city = VALUES(city),
            display_name = VALUES(display_name),
            country = VALUES(country),
            created_at = CURRENT_TIMESTAMP
    ');
    $cacheInsert->execute([
        $lat,
        $lng,
        $city,
        $data['display_name'] ?? $city,
        $address['country'] ?? null
    ]);
    error_log("[reverse_geocode] Cached result for lat=$lat, lng=$lng");
} catch (PDOException $e) {
    error_log("[reverse_geocode] Cache insert failed: " . $e->getMessage());
    // Continue anyway
}

echo json_encode([
    'success'      => true,
    'city'         => $city,
    'display_name' => $data['display_name'] ?? $city,
    'country'      => $address['country'] ?? null,
    'source'       => 'nominatim',
    'timestamp'    => date('Y-m-d H:i:s'),
]);
