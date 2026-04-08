<?php
/**
 * API: Obtener títulos de puntos de interés agrupados
 * 
 * Retorna los títulos que tienen más de un registro en el viaje especificado.
 * Usado para seleccionar agrupaciones de POI al crear rutas automáticas.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Validar trip_id
if (!isset($_GET['trip_id']) || !is_numeric($_GET['trip_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'trip_id requerido']);
    exit;
}

$trip_id = (int) $_GET['trip_id'];

try {
    $db = getDB();
    
    // Obtener títulos con más de un punto de interés
    $stmt = $db->prepare('
        SELECT title, COUNT(*) as point_count
        FROM points_of_interest
        WHERE trip_id = ?
        GROUP BY title
        HAVING point_count > 1
        ORDER BY title ASC
    ');
    $stmt->execute([$trip_id]);
    $results = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch (PDOException $e) {
    error_log('Error al obtener títulos de POI: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}