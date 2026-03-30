# Links Externos para Puntos de Interés (POI Links)

## Descripción General

Los POI Links permiten asociar links externos tipificados a cada Punto de Interés: sitio web, Google Maps, Instagram, TripAdvisor, Booking, etc. Cada link tiene un tipo enumerado que determina el ícono y el color con el que se renderiza, tanto en el timeline de la página del viaje como en los popups del mapa.

---

## Tipos de Link Soportados

| Tipo           | Label          | Color     |
|----------------|----------------|-----------|
| `website`      | Website        | `#0d6efd` |
| `google_maps`  | Google Maps    | `#ea4335` |
| `instagram`    | Instagram      | `#c13584` |
| `facebook`     | Facebook       | `#1877f2` |
| `twitter`      | Twitter / X    | `#000000` |
| `tripadvisor`  | TripAdvisor    | `#34e0a1` |
| `booking`      | Booking.com    | `#003580` |
| `airbnb`       | Airbnb         | `#ff5a5f` |
| `youtube`      | YouTube        | `#ff0000` |
| `other`        | Enlace         | `#6c757d` |

El tipo `other` acepta una etiqueta personalizada (`label`) para casos no cubiertos por los tipos predefinidos.

---

## Arquitectura Técnica

### Base de Datos

**Tabla**: `poi_links`

```sql
CREATE TABLE poi_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id      INT UNSIGNED NOT NULL,
    link_type   ENUM('website','google_maps','instagram','facebook',
                     'twitter','tripadvisor','booking','airbnb','youtube','other')
                NOT NULL DEFAULT 'website',
    url         VARCHAR(500) NOT NULL,
    label       VARCHAR(100) DEFAULT NULL,   -- texto custom (útil para 'other')
    sort_order  TINYINT UNSIGNED DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE
);
```

**Decisión de diseño — tabla separada vs columnas en `points_of_interest`:**
- Un POI puede tener múltiples links del mismo o distinto tipo
- Evita agregar 10+ columnas nullable a `points_of_interest`
- Agregar un nuevo tipo en el futuro solo requiere modificar el `ENUM` en la tabla y la constante `TYPES` del modelo

### Modelo PHP

**Archivo**: `src/models/PoiLink.php`

**Métodos públicos:**

| Método | Descripción |
|--------|-------------|
| `getByPoiId(int $poi_id): array` | Devuelve todos los links de un POI ordenados por `sort_order` |
| `replaceForPoi(int $poi_id, array $links): bool` | Reemplaza todos los links (DELETE + INSERT en transacción) |
| `toApiArray(array $dbRows): array` | Convierte rows de BD a formato JSON para el frontend |
| `getSvg(string $type, int $size): string` | Genera el SVG inline para un tipo de link |
| `getTypes(): array` | Devuelve la lista de tipos con su metadata |

**Constante `TYPES`:** define para cada tipo el label, color hexadecimal y los `<path>` SVG (Bootstrap Icons, viewBox 0 0 16 16). Esto permite renderizar íconos sin depender de ninguna fuente de iconos externa.

**Estrategia de guardado `replaceForPoi`:**
Usa una transacción que primero borra todos los links existentes del POI y luego inserta los nuevos. Esto simplifica la lógica del formulario y garantiza consistencia (no hay links "huérfanos" por reordenamiento).

### API

**Archivo**: `api/get_all_data.php`

Cada POI en la respuesta JSON incluye un campo `links`:

```json
{
  "id": 42,
  "title": "Torre Eiffel",
  "links": [
    {
      "type":      "website",
      "url":       "https://www.toureiffel.paris",
      "label":     "Website",
      "color":     "#0d6efd",
      "svg_paths": "<path d=\"...\"/>"
    },
    {
      "type":      "google_maps",
      "url":       "https://maps.google.com/?q=Torre+Eiffel",
      "label":     "Google Maps",
      "color":     "#ea4335",
      "svg_paths": "<path d=\"...\"/>"
    }
  ]
}
```

Los `svg_paths` son los contenidos internos del `<svg>`, listos para inyectarse en el HTML del popup.

---

## Migración e Instalación

### Opción 1 — Desde el navegador (recomendado)

Navegar a:

```
http://localhost:8080/TravelMap/install/migrate_poi_links.php
```

El script verifica si la tabla `poi_links` ya existe antes de ejecutar nada. Si existe, informa y no modifica nada. Si no existe, ejecuta el SQL y muestra la estructura resultante.

### Opción 2 — CLI con MySQL

```bash
mysql -u root -p travelmap < install/migration_poi_links.sql
```

### Opción 3 — Docker exec

```bash
docker exec -i travelmap-db mysql -u root -proot travelmap < install/migration_poi_links.sql
```

### Opción 4 — PhpMyAdmin

1. Abrir `http://localhost:8081`
2. Seleccionar la base de datos `travelmap`
3. Ir a la pestaña **SQL**
4. Pegar el contenido de `install/migration_poi_links.sql` y ejecutar

### Opción 5 — SQL manual

```sql
CREATE TABLE IF NOT EXISTS poi_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id      INT UNSIGNED NOT NULL,
    link_type   ENUM('website','google_maps','instagram','facebook',
                     'twitter','tripadvisor','booking','airbnb','youtube','other')
                NOT NULL DEFAULT 'website',
    url         VARCHAR(500) NOT NULL,
    label       VARCHAR(100) DEFAULT NULL,
    sort_order  TINYINT UNSIGNED DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
    INDEX idx_poi_id (poi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Nota de seguridad:** después de ejecutar la migración vía browser, considerar restringir o eliminar el archivo `install/migrate_poi_links.php`.

---

## Guía de Uso (Administrador)

### Agregar links a un POI

1. Ir a **Admin → Points of Interest**
2. Crear un nuevo punto o editar uno existente
3. En la sección **"Links Externos"**, hacer clic en **"Agregar link"**
4. Seleccionar el tipo en el dropdown (Website, Google Maps, Instagram, etc.)
5. Ingresar la URL completa (incluyendo `https://`)
6. Opcionalmente, ingresar una etiqueta personalizada (solo se muestra en el tooltip)
7. Repetir para más links
8. Guardar el formulario

### Reordenar links

El orden de aparición refleja el orden en que se agregaron los links en el formulario. Para reordenar, eliminar y volver a agregar en el orden deseado.

### Eliminar un link

Hacer clic en el botón 🗑️ al final de la fila del link y guardar.

---

## Visualización

### Timeline del viaje (`trip.php`)

Los links aparecen como fila de íconos SVG debajo del nombre del POI en el panel izquierdo. Cada ícono:
- Tiene el color propio del tipo (rojo para Google Maps, azul para Facebook, etc.)
- Al pasar el cursor muestra el label en tooltip
- Abre el link en una pestaña nueva (`target="_blank"`)

### Popup del mapa (MapLibre y Leaflet)

Los links aparecen como una fila de íconos entre la descripción del POI y las coordenadas, con el mismo comportamiento que en el timeline.

---

## Internacionalización

Claves agregadas en `lang/en.json` y `lang/es.json` bajo la sección `points`:

| Clave | Español | Inglés |
|-------|---------|--------|
| `points.external_links` | Links Externos | External Links |
| `points.add_link` | Agregar link | Add link |
| `points.link_label_optional` | Etiqueta (opcional) | Label (optional) |

---

## Archivos Modificados / Creados

### Backend (PHP)
| Archivo | Estado |
|---------|--------|
| `src/models/PoiLink.php` | NUEVO |
| `install/migration_poi_links.sql` | NUEVO |
| `install/migrate_poi_links.php` | NUEVO |
| `admin/point_form.php` | Modificado |
| `api/get_all_data.php` | Modificado |
| `trip.php` | Modificado |

### Frontend (JS / CSS)
| Archivo | Estado |
|---------|--------|
| `assets/js/public_map.js` | Modificado |
| `assets/js/public_map_leaflet.js` | Modificado |
| `assets/css/public_map.css` | Modificado |
| `assets/css/trip.css` | Modificado |

### Internacionalización
| Archivo | Estado |
|---------|--------|
| `lang/en.json` | Modificado |
| `lang/es.json` | Modificado |

---

## Agregar un Nuevo Tipo de Link

1. Agregar la entrada en la constante `TYPES` de `src/models/PoiLink.php`:
   ```php
   'tiktok' => [
       'label'     => 'TikTok',
       'color'     => '#010101',
       'svg_paths' => '<path d="..."/>',
   ],
   ```
2. Agregar `'tiktok'` al `ENUM` en la tabla `poi_links` (migración ALTER TABLE):
   ```sql
   ALTER TABLE poi_links
   MODIFY COLUMN link_type ENUM(
       'website','google_maps','instagram','facebook',
       'twitter','tripadvisor','booking','airbnb','youtube','tiktok','other'
   ) NOT NULL DEFAULT 'website';
   ```
3. No hay cambios necesarios en el frontend: el SVG y el color viajan en el JSON de la API.

---

## Solución de Problemas

### Los links no aparecen después de guardar

- Verificar que la migración se ejecutó correctamente (`SHOW TABLES LIKE 'poi_links'`)
- Verificar que las URLs son válidas (deben incluir `https://`)
- Revisar los logs de PHP para errores en `PoiLink::replaceForPoi()`

### Error al correr la migración desde el navegador

- Verificar que `config/db.php` tiene las credenciales correctas
- Si usás Docker, asegurarse de que el contenedor `db` está corriendo: `docker-compose ps`
- Verificar que el usuario de MySQL tiene permisos de `CREATE TABLE`

### Los íconos no se ven en el mapa

- Verificar que el campo `svg_paths` llega en la respuesta de `api/get_all_data.php`
- Los SVGs son inline (no dependen de Bootstrap Icons), por lo que no hay dependencias externas que puedan fallar
- Abrir DevTools → Network → buscar la respuesta de `get_all_data.php` y verificar el campo `links` en los puntos

---

## Compatibilidad

- ✅ **PHP**: 8.0+
- ✅ **MySQL**: 5.7+ / MariaDB 10.3+
- ✅ **Navegadores**: Modernos con soporte para `<template>` HTML5 (Chrome 26+, Firefox 22+, Safari 8+)
- ✅ **MapLibre GL**: v2.x / v3.x
- ✅ **Leaflet**: v1.x

---

**Última actualización**: Marzo 2026
