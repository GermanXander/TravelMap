# Sistema de Migraciones – TravelMap

## Índice

1. [Estructura de archivos](#1-estructura-de-archivos)
2. [Cómo funciona](#2-cómo-funciona)
3. [Instalación nueva](#3-instalación-nueva)
4. [Actualización de instalación existente](#4-actualización-de-instalación-existente)
5. [Cómo agregar una nueva migración](#5-cómo-agregar-una-nueva-migración)
6. [Tabla schema_migrations](#6-tabla-schema_migrations)
7. [Migraciones existentes](#7-migraciones-existentes)
8. [Validación de esquema](#8-validación-de-esquema)
9. [Referencia: MigrationRunner](#9-referencia-migrationrunner)

---

## 1. Estructura de archivos

```
install/
├── index.php               ← Instalador/actualizador web
├── MigrationRunner.php     ← Motor de migraciones
├── generate_thumbnails.php ← Utilidad: regenerar miniaturas existentes
└── migrations/
    ├── 001_initial_schema.php
    ├── 002_settings_table.php
    ├── 003_trip_status.php
    ├── 004_transport_bus_aerial.php
    ├── 005_route_distance.php
    ├── 006_transport_bike.php
    ├── 007_image_settings.php
    ├── 008_thumbnail_settings.php
    ├── 009_site_settings.php
    ├── 010_language_setting.php
    ├── 011_map_style_setting.php
    ├── 012_trip_tags_table.php
    ├── 013_geocode_cache_table.php
    └── 014_poi_links_table.php

database.sql                ← Esquema completo final (fuente de verdad)
```

---

## 2. Cómo funciona

### Principios

| Concepto | Descripción |
|---|---|
| **Fuente de verdad** | `database.sql` representa el esquema final completo y actualizado |
| **Migraciones numeradas** | Cada cambio de esquema es un archivo `NNN_nombre.php` en `install/migrations/` |
| **Registro de aplicadas** | La tabla `schema_migrations` registra qué migraciones fueron aplicadas y cuándo |
| **Auto-detección** | En instalaciones previas al runner, el método `check()` detecta el estado actual del esquema |
| **Idempotencia** | Todas las operaciones son seguras de re-ejecutar (`IF NOT EXISTS`, `INSERT IGNORE`, etc.) |

### Flujo para instalaciones existentes (sin schema_migrations)

Cuando el runner arranca por primera vez en una instalación ya operativa:

1. Crea la tabla `schema_migrations` si no existe
2. Por cada archivo de migración (en orden numérico):
   - Si ya está en `schema_migrations` → **saltar**
   - Si `check()` devuelve `true` → **marcar como aplicada** en la tabla, sin ejecutar `up()`
   - Si `check()` devuelve `false` → **ejecutar `up()`** y marcar como aplicada

Esto garantiza que al correr el runner por primera vez, solo se ejecutan los cambios realmente pendientes.

---

## 3. Instalación nueva

Acceder a `http://[host]/TravelMap/install/` y seguir el asistente:

1. **Requisitos** – Verificación automática de PHP ≥ 7.4, PDO MySQL, permisos de escritura
2. **Configuración** – Ingresar credenciales MySQL y la subcarpeta del proyecto. El instalador escribe `config/db.php` y `config/config.php`
3. **Base de datos** – Ejecuta `database.sql` → crea todas las tablas y datos iniciales → marca todas las migraciones como aplicadas
4. **Administrador** – Crear el primer usuario de acceso
5. **Listo** – Instrucciones finales (eliminar `install/`)

> **Importante**: `database.sql` es la única fuente ejecutada en una instalación nueva. Las migraciones individuales **no** se ejecutan; solo se marcan como "ya aplicadas".

---

## 4. Actualización de instalación existente

Acceder a `http://[host]/TravelMap/install/` con la configuración ya existente.

El instalador mostrará:
- **Estado del esquema**: comparación de tablas/columnas actuales con la estructura esperada
- **Lista de migraciones**: cuáles están aplicadas y cuáles pendientes
- **Botón "Ejecutar migraciones"**: aplica todas las pendientes en orden

El runner auto-detecta las migraciones ya aplicadas usando los métodos `check()` de cada clase.

---

## 5. Cómo agregar una nueva migración

### Paso 1 – Crear el archivo de migración

Crear `install/migrations/015_nombre_descriptivo.php` (el prefijo numérico determina el orden de ejecución):

```php
<?php
/**
 * Migration 015: Descripción del cambio
 */
class Migration_015_nombre_descriptivo
{
    public static function id(): string
    {
        return '015_nombre_descriptivo';
    }

    public static function description(): string
    {
        return 'Descripción legible del cambio (máx. 500 caracteres)';
    }

    /**
     * Devuelve true si este cambio ya está aplicado.
     * Se usa para auto-detectar el estado en instalaciones previas.
     * Debe chequear el estado REAL del esquema, no la tabla schema_migrations.
     */
    public static function check(PDO $db): bool
    {
        // Ejemplo: verificar si una columna existe
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'nueva_columna'");
        return (bool) $stmt->fetchColumn();

        // Ejemplo: verificar si un setting existe
        // $stmt = $db->prepare("SELECT 1 FROM settings WHERE setting_key = ?");
        // $stmt->execute(['nuevo_setting']);
        // return (bool) $stmt->fetchColumn();

        // Ejemplo: verificar si una tabla existe
        // $stmt = $db->query("SHOW TABLES LIKE 'nueva_tabla'");
        // return (bool) $stmt->fetchColumn();
    }

    /**
     * Aplica el cambio. Debe ser idempotente (seguro de re-ejecutar).
     * Usar IF NOT EXISTS, INSERT IGNORE, verificaciones previas, etc.
     */
    public static function up(PDO $db): void
    {
        // Ejemplo: agregar columna
        $stmt = $db->query("SHOW COLUMNS FROM trips LIKE 'nueva_columna'");
        if (!$stmt->fetchColumn()) {
            $db->exec("ALTER TABLE trips ADD COLUMN nueva_columna VARCHAR(100) DEFAULT NULL");
        }

        // Ejemplo: agregar setting
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
            VALUES ('nuevo_setting', 'valor_default', 'string', 'Descripción del setting')
        ");
    }
}
```

### Paso 2 – Actualizar database.sql

Agregar el cambio al esquema final en `database.sql`. Este archivo debe reflejar **siempre** el estado final completo de la base de datos.

### Paso 3 – Actualizar expectedSchema() en MigrationRunner

Si la migración agrega una nueva tabla o columna importante, agregarla al método `expectedSchema()` en `MigrationRunner.php`:

```php
private function expectedSchema(): array
{
    return [
        // ... tablas existentes ...
        'nueva_tabla' => ['id', 'col1', 'col2', 'created_at'],
    ];
}
```

### Notas sobre DDL en MySQL

- Las operaciones DDL (`ALTER TABLE`, `CREATE TABLE`, `DROP TABLE`) causan **commit implícito** en MySQL. No se pueden deshacer con rollback.
- Por este motivo, cada migración debe ser **lo más atómica posible** y verificar el estado antes de operar.
- Para renombrar ENUMs (como en migración 003), usar la técnica de columna temporal para preservar los datos.

---

## 6. Tabla schema_migrations

```sql
CREATE TABLE schema_migrations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_id VARCHAR(200) NOT NULL UNIQUE,
    description  VARCHAR(500),
    applied_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

| Campo | Descripción |
|---|---|
| `migration_id` | Identificador único de la migración (ej. `014_poi_links_table`) |
| `description` | Descripción legible |
| `applied_at` | Fecha/hora de aplicación |

---

## 7. Migraciones existentes

| # | ID | Descripción | Fecha aprox. |
|---|---|---|---|
| 001 | `001_initial_schema` | Tablas base: users, trips, routes, points_of_interest | 2025-12 |
| 002 | `002_settings_table` | Tabla settings con configuraciones del sistema | 2025-12-28 |
| 003 | `003_trip_status` | trips.status: reemplaza public/planned por published | 2025-12 |
| 004 | `004_transport_bus_aerial` | routes.transport_type: agrega bus y aerial | 2026-01-02 |
| 005 | `005_route_distance` | routes: agrega is_round_trip y distance_meters | 2026-01-08 |
| 006 | `006_transport_bike` | routes.transport_type: agrega bike | 2026-01-08 |
| 007 | `007_image_settings` | settings: parámetros de procesamiento de imágenes | 2025-12-28 |
| 008 | `008_thumbnail_settings` | settings: parámetros de miniaturas | 2026-01 |
| 009 | `009_site_settings` | settings: título, descripción, favicon, analytics | 2025-12-28 |
| 010 | `010_language_setting` | settings: default_language | 2025-12-30 |
| 011 | `011_map_style_setting` | settings: map_style | 2026-01 |
| 012 | `012_trip_tags_table` | Tabla trip_tags y setting trip_tags_enabled | 2026-01-06 |
| 013 | `013_geocode_cache_table` | Tabla geocode_cache | 2026-01 |
| 014 | `014_poi_links_table` | Tabla poi_links: enlaces externos para POIs | 2026-03-30 |

---

## 8. Validación de esquema

El método `MigrationRunner::validateSchema()` compara el esquema actual contra la estructura esperada definida en `expectedSchema()`.

Verifica:
- Existencia de todas las tablas esperadas
- Existencia de todas las columnas esperadas en cada tabla
- Valores correctos en ENUMs críticos (`trips.status`, `routes.transport_type`)

Acceder desde el instalador web o usar directamente:

```php
require_once __DIR__ . '/install/MigrationRunner.php';
$runner = new MigrationRunner(getDB());
$issues = $runner->validateSchema();
if (empty($issues)) {
    echo "Esquema OK\n";
} else {
    foreach ($issues as $issue) {
        echo "PROBLEMA: {$issue}\n";
    }
}
```

---

## 9. Referencia: MigrationRunner

### Constructor

```php
$runner = new MigrationRunner(PDO $db);
```

Crea la tabla `schema_migrations` si no existe.

### Métodos públicos

| Método | Devuelve | Descripción |
|---|---|---|
| `getStatus()` | `array` | Estado de todas las migraciones. Auto-detecta aplicadas via `check()` |
| `runPending()` | `array` | Ejecuta todas las migraciones pendientes. Devuelve resultados |
| `run(string $class)` | `array` | Ejecuta una migración específica por nombre de clase |
| `markAllAsApplied()` | `void` | Marca todas como aplicadas (usar tras instalación nueva via database.sql) |
| `validateSchema()` | `array` | Valida esquema. Devuelve lista de problemas (vacía = OK) |

### Formato del resultado de run/runPending

```php
[
    'id'          => '014_poi_links_table',
    'description' => 'Tabla poi_links: enlaces externos para POIs',
    'success'     => true,
    'skipped'     => false,  // true si ya estaba aplicada
    'message'     => 'Aplicada correctamente',
]
```
