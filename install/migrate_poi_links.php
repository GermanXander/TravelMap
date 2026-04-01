<?php
/**
 * Migration: Create poi_links table
 *
 * Run this script ONCE from your browser:
 * http://localhost/TravelMap/install/migrate_poi_links.php
 *
 * After execution, delete or protect this file.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - POI Links</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; border-left: 4px solid #28a745; margin: 15px 0; }
        .error   { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; border-left: 4px solid #dc3545; margin: 15px 0; }
        .info    { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; border-left: 4px solid #17a2b8; margin: 15px 0; }
        table    { border-collapse: collapse; width: 100%; }
        th, td   { border: 1px solid #dee2e6; padding: 6px 10px; }
        th       { background: #f8f9fa; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔗 Migration: POI Links</h1>

    <div class="info">
        <strong>ℹ️ Information:</strong> This script creates the <code>poi_links</code> table,
        which stores typed external links (website, Google Maps, Instagram, Wikipedia, etc.) for each Point of Interest.
        If the table already exists, it checks that all required ENUM values are present and applies any missing ones.
    </div>

<?php

// Canonical ENUM values — keep in sync with PoiLink::TYPES and migration_poi_links.sql
const REQUIRED_ENUM_VALUES = [
    'website', 'google_maps', 'instagram', 'facebook',
    'twitter', 'tripadvisor', 'booking', 'airbnb',
    'youtube', 'wikipedia', 'other',
];

try {
    $conn = getDB();

    echo "<h3>Checking database status...</h3>";

    // ── Step 1: create table if it doesn't exist ──────────────────────────────
    $stmt   = $conn->query("SHOW TABLES LIKE 'poi_links'");
    $exists = $stmt->rowCount() > 0;

    if ($exists) {
        echo '<div class="info">';
        echo '<strong>✓ Table <code>poi_links</code> already exists.</strong><br>';
        echo 'Skipping table creation.';
        echo '</div>';
    } else {
        echo "<p>Creating table <code>poi_links</code>...</p>";

        $sqlFile = __DIR__ . '/migration_poi_links.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Migration SQL file not found: " . basename($sqlFile));
        }

        $conn->exec(file_get_contents($sqlFile));

        echo '<div class="success">';
        echo '<strong>✓ Table <code>poi_links</code> created successfully!</strong>';
        echo '</div>';
    }

    // ── Step 2: ensure all ENUM values are present (idempotent ALTER) ─────────
    echo "<h3>Checking ENUM values...</h3>";

    $row        = $conn->query("
        SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'poi_links'
          AND COLUMN_NAME  = 'link_type'
    ")->fetch(PDO::FETCH_ASSOC);

    // Parse current ENUM values from the COLUMN_TYPE string
    // e.g. "enum('website','google_maps',...)"
    preg_match_all("/'([^']+)'/", $row['COLUMN_TYPE'], $matches);
    $currentValues = $matches[1];

    $missing = array_diff(REQUIRED_ENUM_VALUES, $currentValues);

    if (empty($missing)) {
        echo '<div class="info">';
        echo '<strong>✓ All ENUM values are present.</strong> No ALTER needed.';
        echo '</div>';
    } else {
        echo '<p>Missing ENUM values: <code>' . implode(', ', $missing) . '</code>. Running ALTER TABLE...</p>';

        // Rebuild the full ENUM in the correct order (other always last)
        $newEnum = "ENUM('" . implode("','", REQUIRED_ENUM_VALUES) . "')";

        $conn->exec("
            ALTER TABLE poi_links
            MODIFY COLUMN link_type {$newEnum} NOT NULL DEFAULT 'website'
        ");

        echo '<div class="success">';
        echo '<strong>✓ ENUM updated successfully!</strong> Added: <code>' . implode(', ', $missing) . '</code>';
        echo '</div>';
    }

    // Show current table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE poi_links")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><b>{$col['Field']}</b></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? '<em>NULL</em>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo '<div class="error"><strong>❌ Database Error:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (Exception $e) {
    echo '<div class="error"><strong>❌ Error:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
}

?>

    <p style="margin-top: 30px;">
        <a href="../index.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Go to Home</a>
    </p>
</div>
</body>
</html>
