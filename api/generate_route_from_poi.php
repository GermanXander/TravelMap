<?php
/**
 * API: Generar ruta desde puntos de interés agrupados por título
 * 
 * Obtiene todos los POI con un título específico, los ordena cronológicamente,
 * filtra los que están a menos de 30m, y genera una ruta walk usando GraphHopper.
 * Usa Server-Sent Events (SSE) para reportar progreso.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar si es request SSE o JSON normal
$isSSE = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/event-stream') !== false;

if ($isSSE) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
}

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['trip_id']) || !isset($input['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'trip_id y title requeridos']);
    exit;
}

$trip_id = (int) $input['trip_id'];
$title = $input['title'];
$is_round_trip = isset($input['is_round_trip']) && $input['is_round_trip'] === true;

// API Key de GraphHopper (obtener desde config)
$graphhopper_api_key = GRAPHHOPPER_API_KEY;

// Configuración de batching
$BATCH_SIZE = 5;
$MIN_DISTANCE_METERS = 20;

/**
 * Envía evento SSE al cliente
 */
function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    @ob_flush();
    flush();
}

/**
 * Calcula distancia Haversine entre dos puntos
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Filtra puntos que están a menos de $minDistance metros entre sí
 */
function filterClosePoints($points, $minDistance) {
    if (count($points) <= 1) return $points;
    
    $filtered = [$points[0]];
    
    for ($i = 1; $i < count($points); $i++) {
        $lastFiltered = end($filtered);
        $distance = haversineDistance(
            $lastFiltered['latitude'], $lastFiltered['longitude'],
            $points[$i]['latitude'], $points[$i]['longitude']
        );
        
        if ($distance >= $minDistance) {
            $filtered[] = $points[$i];
        }
    }
    
    return $filtered;
}

/**
 * Llama a GraphHopper API con un batch de puntos
 * Implementa retry con backoff exponencial para manejar límites de rate
 */
function callGraphHopper($points, $apiKey, $maxRetries = 3) {
    $params = [];
    foreach ($points as $p) {
        $params[] = 'point=' . $p['latitude'] . ',' . $p['longitude'];
    }
    
    $url = 'https://graphhopper.com/api/1/route?' . implode('&', $params) .
           '&vehicle=foot&points_encoded=false&key=' . $apiKey;
    
    error_log('[GraphHopper] URL completa: ' . $url);
    error_log('[GraphHopper] Puntos en batch: ' . count($points));
    
    $attempt = 0;
    $baseDelay = 2000000; // 2 segundos en microsegundos
    
    while ($attempt < $maxRetries) {
        if ($attempt > 0) {
            $delay = $baseDelay * pow(2, $attempt - 1); // Backoff exponencial: 2s, 4s, 8s
            error_log('[GraphHopper] Reintento ' . $attempt . ' de ' . $maxRetries . '. Esperando ' . ($delay / 1000000) . ' segundos...');
            usleep($delay);
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'method' => 'GET',
                'header' => 'Accept: application/json'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        // Log del status HTTP
        if (isset($http_response_header)) {
            error_log('[GraphHopper] HTTP Status: ' . $http_response_header[0]);
        }
        
        if ($response === false) {
            error_log('[GraphHopper] ERROR: file_get_contents retornó false');
            error_log('[GraphHopper] Error info: ' . print_r(error_get_last(), true));
            $attempt++;
            continue;
        }
        
        error_log('[GraphHopper] Respuesta cruda (primeros 500 chars): ' . substr($response, 0, 500));
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[GraphHopper] Error JSON decode: ' . json_last_error_msg());
            $attempt++;
            continue;
        }
        
        // Verificar si es un error de rate limit
        if (isset($data['message']) && strpos($data['message'], 'limit') !== false) {
            error_log('[GraphHopper] Rate limit detectado. Mensaje: ' . $data['message']);
            $attempt++;
            continue;
        }
        
        if (!$data || !isset($data['paths']) || empty($data['paths'])) {
            error_log('[GraphHopper] Respuesta sin paths. Data completa: ' . $response);
            
            // Log de errores específicos de GraphHopper
            if (isset($data['message'])) {
                error_log('[GraphHopper] Mensaje de error: ' . $data['message']);
            }
            if (isset($data['hints'])) {
                error_log('[GraphHopper] Hints: ' . json_encode($data['hints']));
            }
            
            $attempt++;
            continue;
        }
        
        error_log('[GraphHopper] Ruta obtenida exitosamente. Distancia: ' . ($data['paths'][0]['distance'] ?? 'N/A') . 'm');
        
        return $data['paths'][0];
    }
    
    error_log('[GraphHopper] Falló después de ' . $maxRetries . ' intentos');
    return null;
}

try {
    $db = getDB();
    
    // Obtener todos los puntos con el título especificado, ordenados por visit_date
    $stmt = $db->prepare('
        SELECT id, title, latitude, longitude, visit_date
        FROM points_of_interest
        WHERE trip_id = ? AND title = ?
        ORDER BY visit_date ASC
    ');
    $stmt->execute([$trip_id, $title]);
    $points = $stmt->fetchAll();
    
    if (count($points) < 2) {
        echo json_encode([
            'success' => false,
            'error' => 'Se necesitan al menos 2 puntos para crear una ruta'
        ]);
        exit;
    }
    
    // Filtrar puntos cercanos (<30m)
    $filteredPoints = filterClosePoints($points, $MIN_DISTANCE_METERS);
    
    if (count($filteredPoints) < 2) {
        echo json_encode([
            'success' => false,
            'error' => 'Los puntos están demasiado cerca entre sí para crear una ruta'
        ]);
        exit;
    }
    
    // Generar ruta con batching
    $allCoordinates = [];
    $totalDistance = 0;
    
    // Calcular batches con solape
    // Si es ida y vuelta, agregar el primer punto al final del último batch para que GraphHopper calcule la ruta walk
    $batches = [];
    for ($i = 0; $i < count($filteredPoints); $i += ($BATCH_SIZE - 1)) {
        $batch = array_slice($filteredPoints, $i, $BATCH_SIZE);
        if (count($batch) >= 2) {
            $batches[] = $batch;
        }
        // Si el batch tiene menos de BATCH_SIZE puntos, es el último
        if (count($batch) < $BATCH_SIZE) {
            break;
        }
    }
    
    // Si es ida y vuelta, agregar el primer punto al último batch para que GraphHopper calcule la ruta walk
    if ($is_round_trip && count($batches) > 0) {
        $lastBatchIndex = count($batches) - 1;
        $batches[$lastBatchIndex][] = $filteredPoints[0];
        error_log('[POI Route] Ida y vuelta activado. Primer punto agregado al último batch para cálculo de ruta walk.');
    }
    
    $totalBatches = count($batches);
    
    // Enviar progreso inicial
    if ($isSSE) {
        sendSSE([
            'type' => 'progress',
            'current' => 0,
            'total' => $totalBatches,
            'message' => 'Iniciando generación de ruta...'
        ]);
    }
    
    // Llamar a GraphHopper para cada batch
    $DELAY_BETWEEN_BATCHES = 2000000; // 2 segundos en microsegundos
    
    foreach ($batches as $batchIndex => $batch) {
        // Delay entre requests para respetar límites de GraphHopper
        if ($batchIndex > 0) {
            error_log('[GraphHopper] Esperando 2 segundos antes del siguiente batch para respetar límites de API...');
            
            if ($isSSE) {
                sendSSE([
                    'type' => 'progress',
                    'current' => $batchIndex,
                    'total' => $totalBatches,
                    'message' => 'Esperando límite de API...'
                ]);
            }
            
            usleep($DELAY_BETWEEN_BATCHES);
        }
        
        error_log('[GraphHopper] Procesando batch ' . ($batchIndex + 1) . ' de ' . $totalBatches);
        
        if ($isSSE) {
            sendSSE([
                'type' => 'progress',
                'current' => $batchIndex + 1,
                'total' => $totalBatches,
                'message' => 'Procesando lote ' . ($batchIndex + 1) . ' de ' . $totalBatches . '...'
            ]);
        }
        
        $path = callGraphHopper($batch, $graphhopper_api_key);
        
        if ($path === null) {
            if ($isSSE) {
                sendSSE([
                    'type' => 'error',
                    'error' => 'Error al conectar con GraphHopper para el batch ' . ($batchIndex + 1)
                ]);
            }
            echo json_encode([
                'success' => false,
                'error' => 'Error al conectar con GraphHopper para el batch ' . ($batchIndex + 1)
            ]);
            exit;
        }
        
        $coords = $path['points']['coordinates'];
        $distance = $path['distance'];
        
        // Si no es el primer batch, eliminar primera coordenada (solape)
        if ($batchIndex > 0) {
            array_shift($coords);
        }
        
        // Si no es el último batch, eliminar la última coordenada para evitar trayectos dobles
        // El último punto de este batch será el primero del siguiente batch
        if ($batchIndex < $totalBatches - 1) {
            array_pop($coords);
        }
        
        $allCoordinates = array_merge($allCoordinates, $coords);
        $totalDistance += $distance;
    }
    
    // Construir GeoJSON
    $geojson = [
        'type' => 'Feature',
        'properties' => [],
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => $allCoordinates
        ]
    ];
    
    // Guardar ruta en la base de datos
    $geojson_json = json_encode($geojson);
    $distance_meters = round($totalDistance);
    $is_round_trip_int = $is_round_trip ? 1 : 0;
    
    error_log('[DB] Guardando ruta en BD para trip_id: ' . $trip_id);
    error_log('[DB] Distancia: ' . $distance_meters . 'm');
    error_log('[DB] Ida y vuelta: ' . ($is_round_trip ? 'Sí' : 'No'));
    error_log('[DB] GeoJSON length: ' . strlen($geojson_json));
    
    if ($isSSE) {
        sendSSE([
            'type' => 'progress',
            'current' => $totalBatches,
            'total' => $totalBatches,
            'message' => 'Guardando en base de datos...'
        ]);
    }
    
    try {
        $stmt = $db->prepare('
            INSERT INTO routes (trip_id, transport_type, geojson_data, is_round_trip, distance_meters, color)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        $result = $stmt->execute([
            $trip_id,
            'walk',
            $geojson_json,
            $is_round_trip_int,
            $distance_meters,
            '#44FF44'
        ]);
        
        $route_id = $db->lastInsertId();
        
        error_log('[DB] Resultado INSERT: ' . ($result ? 'SUCCESS' : 'FAILED'));
        error_log('[DB] Route ID insertado: ' . $route_id);
        
        if ($isSSE) {
            sendSSE([
                'type' => 'complete',
                'success' => true,
                'route_id' => $route_id,
                'geojson' => $geojson,
                'distance_meters' => $distance_meters,
                'points_used' => count($filteredPoints),
                'points_original' => count($points)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'route_id' => $route_id,
                'geojson' => $geojson,
                'distance_meters' => $distance_meters,
                'points_used' => count($filteredPoints),
                'points_original' => count($points)
            ]);
        }
        
    } catch (PDOException $e) {
        error_log('[DB] ERROR al guardar ruta: ' . $e->getMessage());
        
        if ($isSSE) {
            sendSSE([
                'type' => 'complete',
                'success' => true,
                'warning' => 'Ruta generada pero no guardada en BD: ' . $e->getMessage(),
                'geojson' => $geojson,
                'distance_meters' => $distance_meters,
                'points_used' => count($filteredPoints),
                'points_original' => count($points)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'warning' => 'Ruta generada pero no guardada en BD: ' . $e->getMessage(),
                'geojson' => $geojson,
                'distance_meters' => $distance_meters,
                'points_used' => count($filteredPoints),
                'points_original' => count($points)
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log('Error al generar ruta desde POI: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
