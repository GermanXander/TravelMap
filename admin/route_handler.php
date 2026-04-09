<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // CAMBIO AQUÍ: En lugar de new Database(), usamos el método estático
    $db = Database::getInstance(); 
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
// Dentro del switch($_GET['action']) o switch($_POST['action'])

        case 'get_existing_routes':
            $trip_id = (int)$_GET['trip_id'];
            $stmt = $conn->prepare("SELECT id, transport_type, distance_meters FROM routes WHERE trip_id = :trip_id ORDER BY id DESC");
            $stmt->execute([':trip_id' => $trip_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_coords_from_route':
            $route_id = (int)$_GET['route_id'];
            $stmt = $conn->prepare("SELECT geojson_data FROM routes WHERE id = :route_id");
            $stmt->execute([':route_id' => $route_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $geojson = json_decode($row['geojson_data'], true);
                // Extraemos las coordenadas. Dependiendo de cómo guardes el GeoJSON, 
                // usualmente están en geometry.coordinates
                $coords = [];
                if (isset($geojson['geometry']['coordinates'])) {
                    foreach ($geojson['geometry']['coordinates'] as $coord) {
                        // OSRM usa [lon, lat], lo convertimos a objeto para el JS
                        $coords[] = ['longitude' => $coord[0], 'latitude' => $coord[1]];
                    }
                }
                echo json_encode($coords);
            } else {
                echo json_encode([]);
            }
            break;

        case 'save_route':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $trip_id = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;
                $geojson_str = $_POST['geojson_data'] ?? '';
                $distance = isset($_POST['distance']) ? (int)$_POST['distance'] : 0;

                $allowed_transport = ['plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial'];
                $transport_type = in_array($_POST['transport_type'] ?? '', $allowed_transport)
                    ? $_POST['transport_type']
                    : 'car';

                require_once __DIR__ . '/../src/models/Route.php';
                $color = Route::getColorByTransport($transport_type);

                // La consulta ahora incluye la distancia real calculada por OSRM
                $query = "INSERT INTO routes (trip_id, transport_type, geojson_data, color, distance_meters) 
                          VALUES (:trip_id, :transport_type, :geojson_data, :color, :distance)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
                $stmt->bindParam(':transport_type', $transport_type, PDO::PARAM_STR);
                $stmt->bindParam(':geojson_data', $geojson_str, PDO::PARAM_STR);
                $stmt->bindParam(':color', $color, PDO::PARAM_STR);
                $stmt->bindParam(':distance', $distance, PDO::PARAM_INT);
                
                $success = $stmt->execute();
                echo json_encode(['status' => $success ? 'success' : 'error']);
            }
            break;
        default:
            echo json_encode(['error' => 'Acción no definida']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en el servidor',
        'message' => $e->getMessage()
    ]);
}