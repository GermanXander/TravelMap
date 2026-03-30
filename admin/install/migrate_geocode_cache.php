<?php
/**
 * Migración: Tabla de Cache de Geocodificación
 *
 * Crea la tabla geocode_cache para almacenar resultados de búsquedas previas
 * a Nominatim y así evitar rate limiting.
 *
 * URL: /admin/install/migrate_geocode_cache.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

// Validar acceso (solo si no hay base de datos o está mal configurada)
// En producción, deberías restringir esto más

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración: Cache de Geocodificación</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #2e7d32;
        }
        .error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #c62828;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        h3 {
            color: #1976d2;
            margin-top: 25px;
        }
        p {
            line-height: 1.6;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗂️ Migración: Cache de Geocodificación</h1>
        
        <div class="info">
            <strong>ℹ️ Propósito:</strong> Esta migración crea la tabla <code>geocode_cache</code> 
            para almacenar resultados de búsquedas previas a Nominatim, evitando rate limiting (máx 1 req/seg).
        </div>

<?php

try {
    $conn = getDB();
    
    echo "<h3>Verificando estado de la base de datos...</h3>";
    
    // Check if table already exists
    $stmt = $conn->query("SHOW TABLES LIKE 'geocode_cache'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo '<div class="info">';
        echo '<strong>✓ Tabla <code>geocode_cache</code> ya existe.</strong><br>';
        echo 'Saltando creación. <a href="#stats">Ver estadísticas →</a>';
        echo '</div>';
    } else {
        echo "<h3>Creando tabla <code>geocode_cache</code>...</h3>";
        echo "<p>Por favor espera...</p>";
        
        // Create table
        $sql = file_get_contents(__DIR__ . '/../../install/migration_geocode_cache.sql');
        
        // Execute statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );
        
        foreach ($statements as $statement) {
            $conn->exec($statement);
        }
        
        echo '<div class="success">';
        echo '<strong>✓ Tabla creada exitosamente!</strong><br>';
        echo 'Se incluyen 2 registros de prueba (Buenos Aires, New York)';
        echo '</div>';
    }
    
    // Show statistics
    echo '<h3 id="stats">📊 Estadísticas</h3>';
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM geocode_cache");
    $count = $stmt->fetch()['count'];
    
    echo "<p><strong>Registros en cache:</strong> $count</p>";
    
    $stmt = $conn->query("
        SELECT 
            DATE(created_at) as fecha, 
            COUNT(*) as cantidad 
        FROM geocode_cache 
        GROUP BY DATE(created_at) 
        ORDER BY fecha DESC 
        LIMIT 5
    ");
    $recent = $stmt->fetchAll();
    
    if (!empty($recent)) {
        echo '<p><strong>Entradas recientes:</strong></p>';
        echo '<ul>';
        foreach ($recent as $row) {
            echo '<li>' . htmlspecialchars($row['fecha']) . ': ' . (int)$row['cantidad'] . ' registros</li>';
        }
        echo '</ul>';
    }
    
    echo '<h3>📋 Próximos pasos</h3>';
    echo '<ol>';
    echo '<li>El cache de geocodificación está listo. Los resultados se guardarán automáticamente.</li>';
    echo '<li>Para limpiar caché antiguo, ejecuta: <code>DELETE FROM geocode_cache WHERE created_at &lt; DATE_SUB(NOW(), INTERVAL 30 DAY)</code></li>';
    echo '<li>O usa el siguiente comando en CRON (diaria): <code>0 2 * * * mysql -u root -p[PASSWORD] TravelMap -e "DELETE FROM geocode_cache WHERE created_at &lt; DATE_SUB(NOW(), INTERVAL 30 DAY)"</code></li>';
    echo '</ol>';
    
    echo '<h3>📈 Variables de monitoreo</h3>';
    echo '<p>Agrégalas al dashboard de administración:</p>';
    echo '<ul>';
    echo '<li><code>SELECT COUNT(*) FROM geocode_cache</code> - Total registros en cache</li>';
    echo '<li><code>SELECT COUNT(*) FROM geocode_cache WHERE created_at &gt; DATE_SUB(NOW(), INTERVAL 1 DAY)</code> - Agregados hoy</li>';
    echo '<li><code>SELECT * FROM geocode_cache ORDER BY created_at DESC LIMIT 10</code> - Últimos 10</li>';
    echo '</ul>';
    
    echo '<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">';
    echo '<p><small>Migración completada: ' . date('Y-m-d H:i:s') . '</small></p>';
    
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<strong>✗ Error durante la migración:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
    
    echo '<h3>💡 Solución manual</h3>';
    echo '<p>Si deseas crear la tabla manualmente, ejecuta en MySQL:<br>';
    echo '<code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0; overflow-x: auto;">';
    echo htmlspecialchars(file_get_contents(__DIR__ . '/../../install/migration_geocode_cache.sql'));
    echo '</code></p>';
}

?>
    </div>
</body>
</html>
