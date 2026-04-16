# Changelog

## [1.0.240.1] – 2026-04-16
- Control de acceso mejorado: protección de contraseña en index.php y trip.php
- Admin logueado obtiene acceso directo sin ingresar contraseña
- Nueva función `check_public_access()` en `includes/public_access.php` para verificación unificada de acceso
- Función `is_trip_accessible()` para validar acceso a viajes específicos
- Función `show_public_login_page()` para mostrar página de login con estética consistente
- Interfaz de contraseña mejorada: mismo container/card/logo y fondo que login.php
- trip.php ahora protegido: no se puede bypassear conociendo el ID si la contraseña es requerida
- APIs actualizadas: `get_all_data.php` y `get_trip.php` ahora validan acceso con `check_public_access()`
- Estilos CSS actualizados: form-label, form-group, alert para formularios de login público
- Fondo de página de login unificado con gradientes oscuros (consistente con admin login)
- Fix: Admin con sesión activa ahora obtiene acceso directo a las APIs públicas

## [1.0.240] – 2026-04-16
- Nuevo sistema de restricción de acceso al sitio público con contraseñas compartidas
- Modelo `PasswordShare` para gestión de enlaces compartidos con expiración y activación
- Migración 022: tabla `password_shares` para enlaces compartidos
- Formulario de compartir en admin (`share_form.php`) con generación de enlaces temporales
- Página de gestión de usuarios actualizada con opciones de compartir
- Mejora de seguridad: bloquea accesos a sesiones de contraseñas expiradas o deactivadas
- API `get_all_data.php` actualizada para verificar acceso compartido

## [1.0.239] – 2026-04-15
- Refactor: tabla `links` polimórfica reemplaza a `poi_links` y `route_links`
- Nuevo modelo `Link` con soporte de `entity_type` (`poi`, `route`, `trip`) y `entity_id`
- Migración 021: crea `links`, migra datos existentes y elimina las tablas redundantes
- `database.sql` actualizado para instalaciones nuevas
- Cascade delete manejado en código en `Point::delete()`, `Route::delete()`, `Route::deleteByTripId()` y `Trip::delete()`

## [1.0.238] – 2026-04-15
- Agregar scrollbar a descripciones largas en popups de puntos de interés

## [1.0.237] – 2026-04-15
- Fix: links de rutas no mantenían su tipo al editar (se convertían a "Website")
- Migración 020: columna `show_routes_in_timeline` (NULL/0/1) en tabla `trips`
- Nueva opción por viaje para mostrar u ocultar rutas en el timeline de `trip.php`; si no está configurada, usa el valor por defecto global
- Setting global `trip_timeline_show_routes` (default: ocultar) en la tab de configuración de viaje
- Formulario de viaje: selector de 3 estados para rutas en timeline (por defecto / mostrar / ocultar)
- Rutas sin fecha de inicio excluidas del timeline (antes aparecían al final sin orden)
- Fix: columna "Acciones" demasiado angosta en tabla de rutas del editor de mapa (botones apilados)
- Fix: `database.sql` no incluía la migración 019 (`start_datetime`, `end_datetime` en `routes`)
- Fix i18n: claves `trip_timeline_show_routes` caían en `original_settings` en lugar del objeto `settings` activo en `es.json`

## [1.0.234] – 2026-04-14
- Mejora visual: pantalla para carga de rutas con modal adaptado al diseño general

## [1.0.231] – 2026-04-13
- Modelo Route: nuevos campos `name`, `description`, `image_path` en `create()` y `update()`
- Nuevo modelo `RouteLink` para links externos tipificados en rutas (paralelo a `PoiLink`)
- API `get_all_data.php`: incluye nuevos campos de ruta y links en el response
- Editor de rutas (`trip_edit_map.php`): campos para nombre y descripción de rutas con modal de edición
- Mapa público (MapLibre y Leaflet): popups de rutas muestran nombre y descripción si existen
- Migración 019: nuevos campos `start_datetime` y `end_datetime` en tabla routes
- Editor de rutas: campos de fecha/hora de inicio y fin del trayecto

## [1.0.230] – 2026-04-13
- Toggle de caja de leyenda

## [1.0.223] – 2026-04-11
- Soporte de Google Photos como tipo de link en POI Links

## [1.0.222] – 2026-04-10
- Selector para agrupar/filtrar viajes por tag en el mapa público
- Link al viaje individual desde el popup en el mapa Leaflet
- Carrusel: título de foto fuera del recuadro, click centra el mapa y resalta el marcador
- Sincronización de resaltado de marcadores al abrir/cerrar tooltips en Leaflet
- Zoom y velocidad de animación de vuelo al POI configurables desde ajustes del viaje
- Internacionalización de mensajes de validación y tipos de punto hardcodeados
- Fix de estilo en botón de importador BRouter

## [1.0.211] – 2026-04-09
- Nueva pestaña "Viaje" en configuración: tooltip POI configurable e interacciones del mapa
- Fix de seguridad y calidad en importador GPX

## [1.0.195] – 2026-04-03
- Instalador/actualizador con autenticación de usuario administrador
- Fix de lógica en el flujo del instalador

## [Migración 016] – 2026-04-06
- Visit date separado en campos fecha + hora en el formulario de punto

## [Importador GPX] – 2026-04-08
- Nuevo importador de archivos GPX (GraphHopper / OpenRailRouting)
- Soporte para importar waypoints opcionales
- Descripción para viaje nuevo desde el formulario de importación
- Soporte i18n en la página de importación GPX

## [1.0.181] – 2026-03-30
- POI Links: links externos tipificados en puntos de interés (website, Google Maps, Instagram, TripAdvisor, Booking, Airbnb, Wikipedia, Google Photos, etc.)
- Popup unificado de POI en todos los renderizadores de mapa (MapLibre y Leaflet)
- Importador GPX de OpenRailRouting / GraphHopper
- Geocodificación inversa con caché en base de datos
- Campo de fecha/hora de visita con hora separada
- Fix para evitar inserción de fechas `0000-00-00`
- Soporte multi-idioma en mapas
- Ajuste de thumbnail máximo a 1024px
- Corrección de formato fecha-hora en popups
- Highlight del POI seleccionado en página de viaje individual con sincronización de carrusel

## [1.0.134] – 2026-01-10
- Preferencias de unidad de distancia por usuario (km / millas)
- Sincronización de unidades entre panel admin y mapa público

## [1.0.133] – 2026-01-09
- Cálculo de distancia de rutas con opción de ida/vuelta
- Tipo de transporte "Bicicleta" añadido

## [1.0.118] – 2026-01-02
- Sistema de tags para viajes (hasta 10 tags por viaje)
- Vuelos con curvas aéreas superiores y múltiples puntos
- URLs compartibles con estado del mapa (zoom, centro, viajes visibles, capas activas)
- Fix de popups en mapa público

## [1.0.108] – 2026-01-02
- Importador BRouter CSV para rutas
- Página individual por viaje (`/trip.php?id=X`) con opción de habilitación por viaje
- Panel lateral con resize en página pública
- Soporte proxy/load balancer (encabezado `X-Forwarded-Host`)

## [Initial] – 2025
- Estructura base del proyecto (PHP vanilla, MySQL, MapLibre GL, deck.gl, Leaflet)
- Panel de administración: gestión de viajes, puntos de interés, rutas y usuarios
- Mapa público con clustering (Supercluster), filtros y popups con galería
- Sistema de importación de vuelos desde FlightRadar CSV (agrupación automática por intervalos)
- Importador de estadías Airbnb con geocodificación automática
- Importador EXIF: puntos con coordenadas y fecha extraídos de imágenes
- Sistema multi-idioma (i18n) PHP + JS con archivos JSON por idioma (en, es)
- Estilos de mapa configurables: Positron, Voyager, Dark Matter, OSM Liberty
- Procesamiento automático de imágenes (resize, compresión, thumbnails)
- Caché offline de tiles del mapa via Service Worker
- Instalador/actualizador web con sistema de migraciones numeradas
- Personalización del sitio público: título, descripción, favicon, analytics
- Colores de rutas configurables por tipo de transporte
