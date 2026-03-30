/**
 * Trip Single Page Logic
 */

let map;
let markers = [];
let activeMarkerId = null;
let pendingPopupMarkerId = null;

document.addEventListener('DOMContentLoaded', () => {
    initResizer();
    initMap();
    initInteractions();
    initTimeline();
    initCarouselDrag();

    // Auto-update units if needed
    if (window.UnitManager) {
        UnitManager.updatePageUnits();
    }
});

function initMap() {
    if (typeof TRIP_DATA === 'undefined') {
        console.error('No trip data found');
        return;
    }

    if (MAP_RENDERER === 'leaflet') {
        initLeaflet();
    } else {
        initMapLibre();
    }
}

function initMapLibre() {
    map = new maplibregl.Map({
        container: 'tripMap',
        style: MapConfig.getMapStyleUrl(MAP_STYLE),
        center: [0, 0],
        zoom: 1
    });

    let clusterMarkers = [];
    let supercluster   = null;

    map.on('load', () => {
        const flightData = [];

        // Add routes via shared renderer
        TRIP_DATA.routes.forEach(route => {
            const arcEntry = MapRenderer.addRouteLayer(
                map, route,
                `route-${route.id}`,
                `route-layer-${route.id}`
            );
            if (arcEntry) flightData.push(arcEntry);
        });

        // Render flight arcs via shared renderer
        if (flightData.length > 0) {
            MapRenderer.renderFlightArcs(map, flightData, null);
        }

        // Initialize Supercluster for points
        supercluster = new Supercluster({ radius: 50, maxZoom: 16, minZoom: 0 });

        supercluster.load(TRIP_DATA.points.map(point => ({
            type: 'Feature',
            properties: { ...point, tripColor: TRIP_DATA.color },
            geometry: { type: 'Point', coordinates: [point.longitude, point.latitude] }
        })));


        function updateClusters() {
            clusterMarkers.forEach(m => m.remove());
            clusterMarkers = [];

            const bounds = map.getBounds();
            const zoom   = Math.floor(map.getZoom());

            supercluster.getClusters(
                [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()],
                zoom
            ).forEach(feature => {
                const coords = feature.geometry.coordinates;

                if (feature.properties.cluster) {
                    const el = MapRenderer.createClusterMarkerEl(feature.properties.point_count);
                    el.addEventListener('click', e => {
                        e.stopPropagation();
                        map.easeTo({
                            center: coords,
                            zoom: supercluster.getClusterExpansionZoom(feature.properties.cluster_id)
                        });
                    });
                    clusterMarkers.push(new maplibregl.Marker({ element: el }).setLngLat(coords).addTo(map));
                } else {
                    const point = feature.properties;
                    const el    = MapRenderer.createPointMarkerEl(point);

                    const markerPopup = new maplibregl.Popup({ offset: 25, closeButton: false })
                        .setHTML(`
                            <div style="padding:4px 2px;">
                                <strong>${point.title}</strong>
                                ${point.visit_date ? `<br><small style="color:#64748b">${new Date(point.visit_date).toLocaleDateString()}</small>` : ''}
                            </div>
                        `);

                    const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                        .setLngLat(coords)
                        .setPopup(markerPopup)
                        .addTo(map);

                    markers[point.id] = marker;
                    if (String(point.id) === String(activeMarkerId)) {
                        el.classList.add('marker-selected');
                    }
                    clusterMarkers.push(marker);
                }
            });
        }

        updateClusters();
        map.on('zoom', updateClusters);
        map.on('move', updateClusters);

        // Fit bounds to all content
        const bounds   = new maplibregl.LngLatBounds();
        let hasBounds  = false;

        TRIP_DATA.routes.forEach(route => {
            const geojson = MapConfig.normalizeGeojson(route.geojson);
            const coords  = geojson && geojson.geometry ? geojson.geometry.coordinates || [] : [];
            coords.forEach(coord => { bounds.extend(coord); hasBounds = true; });
        });

        TRIP_DATA.points.forEach(point => {
            if (point.latitude && point.longitude) {
                bounds.extend([point.longitude, point.latitude]);
                hasBounds = true;
            }
        });

        if (hasBounds) {
            map.fitBounds(bounds, { padding: 50 });
        }
    });

    map.addControl(new maplibregl.NavigationControl());
}

function initLeaflet() {
    map = L.map('tripMap').setView([0, 0], 2);

    const tileUrl = MapConfig.RASTER_TILES[MAP_STYLE] || MapConfig.RASTER_TILES['voyager'];

    L.tileLayer(tileUrl, {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    const bounds = L.latLngBounds();
    let hasLayer = false;

    // Helper function to create curved flight paths
    function createCurvedFlightPath(startCoord, endCoord, curvature) {
        const start = L.latLng(startCoord[1], startCoord[0]);
        const end = L.latLng(endCoord[1], endCoord[0]);

        const midLat = (start.lat + end.lat) / 2;
        const midLng = (start.lng + end.lng) / 2;
        const distance = start.distanceTo(end);

        const baseHeight = distance / 5;
        const curveHeight = Math.max(baseHeight, distance * 0.15);
        const offsetDegrees = (curveHeight / 111320) * (curvature || 1);

        const controlPoint = L.latLng(midLat + offsetDegrees, midLng);

        const points = [];
        const segments = Math.max(20, Math.floor(distance / 50000));

        for (let i = 0; i <= segments; i++) {
            const t = i / segments;
            const lat = (1 - t) * (1 - t) * start.lat +
                2 * (1 - t) * t * controlPoint.lat +
                t * t * end.lat;
            const lng = (1 - t) * (1 - t) * start.lng +
                2 * (1 - t) * t * controlPoint.lng +
                t * t * end.lng;
            points.push([lat, lng]);
        }

        return points;
    }

    // Add Routes
    TRIP_DATA.routes.forEach(route => {
        if (!route.geojson) return;

        const geojson        = MapConfig.normalizeGeojson(route.geojson);
        if (!geojson || !geojson.geometry) return;

        const transportType  = route.transport_type || 'car';
        const cfg            = MapConfig.transportConfig[transportType] || MapConfig.transportConfig['car'];
        const coords         = geojson.geometry.coordinates || [];
        const isPlaneRoute   = transportType === 'plane';

        if (isPlaneRoute) {
            const latLngs = [];
            if (coords.length === 2) {
                latLngs.push(...createCurvedFlightPath(coords[0], coords[1], 1));
            } else {
                for (let i = 0; i < coords.length - 1; i++) {
                    latLngs.push(...createCurvedFlightPath(coords[i], coords[i + 1], 0.8));
                }
            }

            const layer = L.polyline(latLngs, {
                color:       cfg.color,
                weight:      2,
                opacity:     0.6,
                dashArray:   '4, 6',
                smoothFactor: 1
            }).addTo(map);

            bounds.extend(layer.getBounds());
            hasLayer = true;
        } else {
            const dashArray = cfg.dashArray ? cfg.dashArray.join(', ') : null;
            const layer = L.geoJSON(geojson, {
                style: {
                    color:     cfg.color,
                    weight:    4,
                    opacity:   0.7,
                    dashArray: dashArray
                }
            }).addTo(map);

            bounds.extend(layer.getBounds());
            hasLayer = true;
        }
    });

    // Add Points with clustering
    const markerClusterGroup = L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true
    });

    TRIP_DATA.points.forEach(point => {
        if (!point.latitude || !point.longitude) return;

        const icon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="
                background-color: ${TRIP_DATA.color || '#3388ff'};
                width: 12px;
                height: 12px;
                border: 2px solid white;
                border-radius: 50%;
                box-shadow: 0 0 4px rgba(0,0,0,0.3);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            "></div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        const marker = L.marker([point.latitude, point.longitude], { icon: icon })
            .bindPopup(`<strong>${point.title}</strong>`);

        marker.on('add', () => {
            if (String(point.id) === String(activeMarkerId)) {
                const el = marker.getElement();
                if (el) {
                    const inner = el.querySelector('div');
                    const color = TRIP_DATA.color || '#3b82f6';
                    if (inner) { inner.style.transform = 'scale(1.7)'; inner.style.boxShadow = `0 0 0 3px ${color}, 0 4px 12px ${color}66`; }
                }
            }
            if (String(point.id) === String(pendingPopupMarkerId)) {
                marker.openPopup();
                pendingPopupMarkerId = null;
            }
        });

        markerClusterGroup.addLayer(marker);
        markers[point.id] = marker;
        bounds.extend(marker.getLatLng());
        hasLayer = true;
    });

    map.addLayer(markerClusterGroup);

    if (hasLayer) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

function initTimeline() {
    const markerEls = document.querySelectorAll('.point-marker');
    if (markerEls.length < 2) return;

    const container = document.querySelector('.timeline-points');
    if (!container) return;

    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.classList.add('timeline-route-svg');
    container.appendChild(svg);

    const tripColor = (typeof TRIP_DATA !== 'undefined' && TRIP_DATA.color) ? TRIP_DATA.color : '#3b82f6';

    function drawPath() {
        svg.innerHTML = '';

        const containerRect = container.getBoundingClientRect();

        const pts = Array.from(markerEls).map(el => {
            const r = el.getBoundingClientRect();
            return {
                x: r.left - containerRect.left + r.width / 2,
                y: r.top - containerRect.top + r.height / 2
            };
        });

        // Build S-curve path alternating left/right between each pair
        let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;

        for (let i = 1; i < pts.length; i++) {
            const prev = pts[i - 1];
            const curr = pts[i];
            const dy = curr.y - prev.y;
            // Alternate the bulge direction each segment
            const flip = i % 2 === 1 ? 1 : -1;
            const amplitude = Math.min(Math.abs(dy) * 0.45, 52);

            const cp1x = (prev.x + amplitude * flip).toFixed(1);
            const cp1y = (prev.y + dy * 0.33).toFixed(1);
            const cp2x = (curr.x + amplitude * flip).toFixed(1);
            const cp2y = (curr.y - dy * 0.33).toFixed(1);

            d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${curr.x.toFixed(1)} ${curr.y.toFixed(1)}`;
        }

        const path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', d);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', tripColor);
        path.setAttribute('stroke-width', '2');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');

        // Animate path drawing then settle into dashed style
        const length = path.getTotalLength ? Math.ceil(path.getTotalLength()) : 2000;
        path.style.setProperty('--path-length', length);
        path.setAttribute('stroke-dasharray', `${length}`);
        path.style.animation = `drawRoutePath 1.2s ease forwards`;

        // After draw animation, switch to dashed appearance
        path.addEventListener('animationend', () => {
            path.setAttribute('stroke-dasharray', '6 5');
            path.setAttribute('opacity', '0.55');
            path.style.animation = '';
        }, { once: true });

        svg.appendChild(path);
    }

    // Draw after layout is painted
    requestAnimationFrame(drawPath);
    window.addEventListener('resize', drawPath);
}

function highlightCarouselItem(pointId) {
    document.querySelectorAll('.media-item').forEach(item => item.classList.remove('active'));
    const target = document.querySelector(`.media-item[data-point-id="${pointId}"]`);
    if (!target) return;
    target.classList.add('active');
    target.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}

function initInteractions() {
    // Timeline clicks
    document.querySelectorAll('.timeline-point').forEach(el => {
        el.addEventListener('click', () => {
            const lat = parseFloat(el.dataset.lat);
            const lng = parseFloat(el.dataset.lng);
            const id = el.dataset.id;

            if (isNaN(lat) || isNaN(lng)) return;

            // Highlight active point in timeline
            document.querySelectorAll('.timeline-point').forEach(p => p.classList.remove('active'));
            el.classList.add('active');

            // Scroll carousel to matching photo
            highlightCarouselItem(id);

            // Highlight active marker on map
            if (activeMarkerId && markers[activeMarkerId]) {
                if (MAP_RENDERER === 'leaflet') {
                    const prevEl = markers[activeMarkerId].getElement();
                    if (prevEl) {
                        const inner = prevEl.querySelector('div');
                        if (inner) { inner.style.transform = ''; inner.style.boxShadow = '0 0 4px rgba(0,0,0,0.3)'; }
                    }
                } else {
                    const prevEl = markers[activeMarkerId].getElement();
                    if (prevEl) prevEl.classList.remove('marker-selected');
                }
            }
            activeMarkerId = id;
            if (markers[id]) {
                if (MAP_RENDERER === 'leaflet') {
                    const curEl = markers[id].getElement();
                    if (curEl) {
                        const inner = curEl.querySelector('div');
                        const color = TRIP_DATA.color || '#3b82f6';
                        if (inner) { inner.style.transform = 'scale(1.7)'; inner.style.boxShadow = `0 0 0 3px ${color}, 0 4px 12px ${color}66`; }
                    }
                } else {
                    const curEl = markers[id].getElement();
                    if (curEl) curEl.classList.add('marker-selected');
                }
            }

            // Fly to map
            if (MAP_RENDERER === 'leaflet') {
                pendingPopupMarkerId = id;
                map.flyTo([lat, lng], 14);
                if (markers[id] && markers[id]._map) { markers[id].openPopup(); pendingPopupMarkerId = null; }
            } else {
                map.flyTo({ center: [lng, lat], zoom: 14 });
                map.once('moveend', () => {
                    if (markers[id]) markers[id].togglePopup();
                });
            }
        });
    });
}

// Lightbox variables
let currentImageIndex = -1;
let galleryItems = [];

// Initialize gallery items
function initGallery() {
    galleryItems = Array.from(document.querySelectorAll('.media-item')).map(item => ({
        url: item.dataset.img,
        title: item.dataset.title || '',
        desc: item.dataset.desc || ''
    }));
}

// Call initGallery on load
document.addEventListener('DOMContentLoaded', () => {
    initGallery();
});

// Lightbox functions (global scope)
window.viewImage = function (url) {
    // Deprecated for direct calls, but kept for compatibility
    // Try to find index in current gallery
    if (galleryItems.length === 0) initGallery();
    const index = galleryItems.findIndex(item => item.url === url);
    if (index !== -1) {
        showLightboxImage(index);
    } else {
        // Fallback for isolated images
        const lightbox = document.getElementById('imageLightbox');
        const img = document.getElementById('lightboxImage');
        img.src = url;
        document.getElementById('lightboxTitle').textContent = '';
        document.getElementById('lightboxDesc').textContent = '';
        document.querySelector('.lightbox-footer').classList.remove('has-content');
        lightbox.style.display = 'flex';
    }
};

window.viewImageFromData = function (element) {
    if (galleryItems.length === 0) initGallery();
    const url = element.dataset.img;
    const index = galleryItems.findIndex(item => item.url === url);

    if (index !== -1) {
        showLightboxImage(index);
    }
};

function showLightboxImage(index) {
    if (index < 0 || index >= galleryItems.length) return;

    currentImageIndex = index;
    const item = galleryItems[index];

    const lightbox = document.getElementById('imageLightbox');
    const img = document.getElementById('lightboxImage');
    const titleEl = document.getElementById('lightboxTitle');
    const descEl = document.getElementById('lightboxDesc');
    const footer = document.querySelector('.lightbox-footer');

    img.src = item.url;
    titleEl.textContent = item.title;

    if (item.desc) {
        descEl.textContent = item.desc;
        descEl.style.display = 'block';
    } else {
        descEl.style.display = 'none';
    }

    if (item.title || item.desc) {
        footer.classList.add('has-content');
    } else {
        footer.classList.remove('has-content');
    }

    lightbox.style.display = 'flex';
}

window.changeImage = function (step) {
    const newIndex = currentImageIndex + step;
    if (newIndex >= 0 && newIndex < galleryItems.length) {
        showLightboxImage(newIndex);
    } else if (newIndex < 0) {
        showLightboxImage(galleryItems.length - 1); // Loop to last
    } else if (newIndex >= galleryItems.length) {
        showLightboxImage(0); // Loop to first
    }
};

window.closeLightbox = function () {
    document.getElementById('imageLightbox').style.display = 'none';
};

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox.style.display === 'flex') {
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') changeImage(-1);
        if (e.key === 'ArrowRight') changeImage(1);
    }
});

// ==================== CAROUSEL DRAG ====================

function initCarouselDrag() {
    const container = document.querySelector('.media-carousel');
    if (!container) return;

    const DRAG_THRESHOLD = 5;
    let isDown      = false;
    let startX      = 0;
    let scrollLeft  = 0;
    let hasDragged  = false;

    // — Mouse drag —
    container.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        isDown     = true;
        hasDragged = false;
        startX     = e.pageX - container.offsetLeft;
        scrollLeft = container.scrollLeft;
        container.classList.add('is-dragging');
        container.style.scrollBehavior = 'auto';
        container.style.scrollSnapType = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        const x    = e.pageX - container.offsetLeft;
        const walk = x - startX;
        if (Math.abs(walk) > DRAG_THRESHOLD) hasDragged = true;
        container.scrollLeft = scrollLeft - walk;
    });

    document.addEventListener('mouseup', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('is-dragging');
        container.style.scrollBehavior = '';
        container.style.scrollSnapType = '';
    });

    // Block the click that fires right after releasing a drag
    container.addEventListener('click', (e) => {
        if (hasDragged) {
            e.stopPropagation();
            e.preventDefault();
            hasDragged = false;
        }
    }, true);

    // — Touch drag —
    let touchStartX    = 0;
    let touchScrollLeft = 0;

    container.addEventListener('touchstart', (e) => {
        touchStartX     = e.touches[0].pageX;
        touchScrollLeft = container.scrollLeft;
        container.style.scrollBehavior = 'auto';
        container.style.scrollSnapType = 'none';
    }, { passive: true });

    container.addEventListener('touchmove', (e) => {
        const walk = touchStartX - e.touches[0].pageX;
        container.scrollLeft = touchScrollLeft + walk;
    }, { passive: true });

    container.addEventListener('touchend', () => {
        container.style.scrollBehavior = '';
        container.style.scrollSnapType = '';
    }, { passive: true });
}

// Carousel Navigation
window.scrollCarousel = function (direction) {
    const container = document.querySelector('.media-carousel');
    if (!container) return;

    const scrollAmount = 300; // Aproximadamente el ancho de un media-item + gap
    const targetScroll = container.scrollLeft + (direction * scrollAmount);

    container.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
};

// ==================== PANEL RESIZER ====================

const RESIZER_STORAGE_KEY = 'travelmap_trip_split';

function initResizer() {
    const resizer   = document.getElementById('tripResizer');
    const leftPanel = document.querySelector('.trip-details');
    const container = document.querySelector('.trip-container');

    if (!resizer || !leftPanel || !container) return;

    // Only active on desktop
    const mq = window.matchMedia('(min-width: 992px)');
    if (!mq.matches) return;

    // Restore saved width
    const saved = localStorage.getItem(RESIZER_STORAGE_KEY);
    if (saved) {
        applyLeftWidth(leftPanel, parseFloat(saved));
    }

    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    resizer.addEventListener('mousedown', (e) => {
        isResizing = true;
        startX = e.clientX;
        startWidth = leftPanel.getBoundingClientRect().width;
        resizer.classList.add('is-resizing');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;
        const containerWidth = container.getBoundingClientRect().width;
        const minPx = 220;
        const maxPx = containerWidth - 220 - 6; // 6px = resizer width
        const newWidth = Math.min(Math.max(startWidth + (e.clientX - startX), minPx), maxPx);
        applyLeftWidth(leftPanel, newWidth);
        // Notify map to resize during drag
        notifyMapResize();
    });

    document.addEventListener('mouseup', () => {
        if (!isResizing) return;
        isResizing = false;
        resizer.classList.remove('is-resizing');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        // Persist preference
        localStorage.setItem(RESIZER_STORAGE_KEY, leftPanel.getBoundingClientRect().width);
        notifyMapResize();
    });
}

function applyLeftWidth(leftPanel, widthPx) {
    leftPanel.style.flex = `0 0 ${widthPx}px`;
    leftPanel.style.width = `${widthPx}px`;
}

function notifyMapResize() {
    if (map) {
        if (typeof map.resize === 'function') {
            map.resize(); // MapLibre GL
        } else if (typeof map.invalidateSize === 'function') {
            map.invalidateSize(); // Leaflet
        }
    }
}
