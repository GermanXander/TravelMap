<?php
/**
 * TravelMap – Migration Runner
 *
 * Gestiona las migraciones de la base de datos.
 * Registra cada migración aplicada en la tabla `schema_migrations`.
 *
 * Cada archivo en install/migrations/ debe definir una clase con:
 *   - id()          : string  — identificador único (ej. '001_initial_schema')
 *   - description() : string  — descripción legible
 *   - check(PDO)    : bool    — true si la migración ya está aplicada (detección por esquema)
 *   - up(PDO)       : void    — aplica la migración
 *
 * Para agregar una nueva migración:
 *   1. Crear install/migrations/NNN_nombre_descriptivo.php
 *   2. Implementar la clase Migration_NNN_nombre_descriptivo con los 4 métodos
 *   3. Listo – el runner la detectará automáticamente
 */
class MigrationRunner
{
    private PDO $db;
    private string $migrationsDir;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->migrationsDir = __DIR__ . '/migrations';
        $this->ensureMigrationsTable();
    }

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Devuelve el estado de todas las migraciones.
     * Auto-detecta migraciones ya aplicadas usando check() si no están
     * registradas en schema_migrations (para instalaciones previas al runner).
     */
    public function getStatus(): array
    {
        $migrations = [];

        foreach ($this->loadMigrationFiles() as $file) {
            $class = $this->classNameFromFile($file);
            require_once $file;

            $tracked = $this->isTrackedAsApplied($class::id());

            // Si no está en la tabla de tracking, usar check() para auto-detectar
            if (!$tracked && $class::check($this->db)) {
                $this->markApplied($class::id(), $class::description());
                $tracked = true;
            }

            $migrations[] = [
                'id'          => $class::id(),
                'description' => $class::description(),
                'applied'     => $tracked,
                'class'       => $class,
                'file'        => basename($file),
            ];
        }

        return $migrations;
    }

    /**
     * Ejecuta todas las migraciones pendientes.
     * Devuelve array de resultados [ id, description, success, message ].
     */
    public function runPending(): array
    {
        $results = [];
        foreach ($this->getStatus() as $migration) {
            if (!$migration['applied']) {
                $results[] = $this->run($migration['class']);
            }
        }
        return $results;
    }

    /**
     * Ejecuta una migración específica por nombre de clase.
     */
    public function run(string $class): array
    {
        $result = [
            'id'          => $class::id(),
            'description' => $class::description(),
            'success'     => false,
            'skipped'     => false,
            'message'     => '',
        ];

        if ($this->isTrackedAsApplied($class::id())) {
            $result['skipped'] = true;
            $result['success'] = true;
            $result['message'] = 'Ya aplicada';
            return $result;
        }

        try {
            $class::up($this->db);
            $this->markApplied($class::id(), $class::description());
            $result['success'] = true;
            $result['message'] = 'Aplicada correctamente';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Marca TODAS las migraciones como aplicadas sin ejecutarlas.
     * Usar después de una instalación fresca vía database.sql.
     */
    public function markAllAsApplied(): void
    {
        foreach ($this->loadMigrationFiles() as $file) {
            $class = $this->classNameFromFile($file);
            require_once $file;
            $this->markApplied($class::id(), $class::description());
        }
    }

    /**
     * Valida el esquema actual contra la estructura esperada.
     * Devuelve lista de problemas (vacía = todo OK).
     */
    public function validateSchema(): array
    {
        $issues = [];

        foreach ($this->expectedSchema() as $table => $columns) {
            $escaped = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $table);
            $stmt = $this->db->query("SHOW TABLES LIKE '{$escaped}'");
            if (!$stmt->fetchColumn()) {
                $issues[] = "Tabla faltante: `{$table}`";
                continue;
            }
            foreach ($columns as $col) {
                $escapedCol = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $col);
                $stmt = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$escapedCol}'");
                if (!$stmt->fetchColumn()) {
                    $issues[] = "Columna faltante: `{$table}`.`{$col}`";
                }
            }
        }

        // Validar ENUMs críticos
        $enumChecks = [
            ['trips', 'status', ["'draft'", "'published'"]],
            ['routes', 'transport_type', ["'plane'", "'car'", "'walk'", "'ship'", "'train'", "'bus'", "'bike'", "'aerial'"]],
        ];
        foreach ($enumChecks as [$table, $col, $expectedValues]) {
            $escapedCol = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $col);
            $stmt = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$escapedCol}'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                foreach ($expectedValues as $val) {
                    if (strpos($row['Type'], $val) === false) {
                        $issues[] = "Valor ENUM faltante en `{$table}`.`{$col}`: {$val}";
                    }
                }
            }
        }

        return $issues;
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    private function ensureMigrationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_id VARCHAR(200) NOT NULL UNIQUE,
                description  VARCHAR(500),
                applied_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_migration_id (migration_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function loadMigrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/*.php');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }

    private function classNameFromFile(string $file): string
    {
        return 'Migration_' . basename($file, '.php');
    }

    private function isTrackedAsApplied(string $migrationId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM schema_migrations WHERE migration_id = ?');
        $stmt->execute([$migrationId]);
        return $stmt->fetchColumn() !== false;
    }

    private function markApplied(string $id, string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO schema_migrations (migration_id, description) VALUES (?, ?)'
        );
        $stmt->execute([$id, $description]);
    }

    /**
     * Esquema esperado final: tabla → columnas requeridas.
     * Mantenlo actualizado cuando agregues migraciones nuevas.
     */
    private function expectedSchema(): array
    {
        return [
            'users' => [
                'id', 'username', 'password_hash', 'created_at',
            ],
            'trips' => [
                'id', 'title', 'description', 'start_date', 'end_date',
                'color_hex', 'status', 'show_routes_in_timeline', 'created_at', 'updated_at',
            ],
            'routes' => [
                'id', 'trip_id', 'transport_type', 'geojson_data',
                'is_round_trip', 'distance_meters', 'color',
                'name', 'description', 'image_path', 'start_datetime', 'end_datetime',
                'created_at', 'updated_at',
            ],
            'points_of_interest' => [
                'id', 'trip_id', 'title', 'description', 'type', 'icon',
                'image_path', 'latitude', 'longitude', 'visit_date', 'created_at', 'updated_at',
            ],
            'settings' => [
                'id', 'setting_key', 'setting_value', 'setting_type',
                'description', 'created_at', 'updated_at',
            ],
            'trip_tags' => [
                'id', 'trip_id', 'tag_name', 'created_at',
            ],
            'geocode_cache' => [
                'id', 'latitude', 'longitude', 'city', 'display_name',
                'country', 'created_at', 'expires_at',
            ],
            'links' => [
                'id', 'entity_type', 'entity_id', 'link_type', 'url', 'label', 'sort_order', 'created_at',
            ],
            'password_shares' => [
                'id', 'password', 'trips', 'description', 'created_at', 'expires_at', 'active',
            ],
            'schema_migrations' => [
                'id', 'migration_id', 'description', 'applied_at',
            ],
        ];
    }
}
