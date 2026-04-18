<?php
/**
 * API Endpoint - Upload Route Image
 * 
 * Procesa el upload de imagen para una ruta
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

try {
    // Verificar que es POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    // Verificar que hay archivo
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
        exit;
    }

    // Procesar upload
    $upload_result = FileHelper::uploadImage($_FILES['image']);

    if ($upload_result['success']) {
        echo json_encode([
            'success' => true,
            'path' => $upload_result['path'],
            'url' => BASE_URL . '/' . $upload_result['path']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $upload_result['error']
        ]);
    }

} catch (Exception $e) {
    error_log('Error upload route image: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar imagen'
    ]);
}