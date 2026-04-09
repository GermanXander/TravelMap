$(document).ready(function() {
    // --- 1. VARIABLES GLOBALES DE INSTANCIA ---
    let map = null;
    let routeLayer = null;
    const METROS_MINIMOS = 25; // Si el GPS se mueve menos de 25m, lo ignoramos para evitar bucles.

    // --- 2. LÓGICA DE PROCESAMIENTO GEOGRÁFICO ---

    // Calcula distancia real entre puntos para saber si hubo movimiento significativo
    function calcularDistancia(c1, c2) {
        const R = 6371000; // Radio de la Tierra en metros
        const dLat = (c2[1] - c1[1]) * Math.PI / 180;
        const dLon = (c2[0] - c1[0]) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 + 
                  Math.cos(c1[1] * Math.PI / 180) * Math.cos(c2[1] * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // Filtra el ruido: Reduce 100 puntos ruidosos a ~15 puntos clave para OSRM
    function limpiarRuta(puntos) {
        if (!puntos || puntos.length < 2) return puntos;

        const limpios = [puntos[0]];
        let ultimoPuntoValido = puntos[0];

        for (let i = 1; i < puntos.length; i++) {
            const distancia = calcularDistancia(ultimoPuntoValido, puntos[i]);
            if (distancia >= METROS_MINIMOS) {
                limpios.push(puntos[i]);
                ultimoPuntoValido = puntos[i];
            }
        }

        // Forzamos que el último punto original siempre esté para cerrar la ruta
        const ultimoOriginal = puntos[puntos.length - 1];
        if (JSON.stringify(limpios[limpios.length - 1]) !== JSON.stringify(ultimoOriginal)) {
            limpios.push(ultimoOriginal);
        }
        return limpios;
    }

    // --- 3. GESTIÓN DEL MAPA Y MODAL ---

    function initMap() {
        if (map !== null) return; // Evita reinicializar y errores de Leaflet
        
        map = L.map('map_canvas').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
    }

    // Mapea tipo de transporte al perfil de OSRM disponible
    function osrmProfile(transportType) {
        const profileMap = {
            'bike': 'bike',
            'walk': 'foot',
            'car': 'car',
            'bus': 'car',
            'train': 'car',
            'plane': 'car',
            'ship': 'car',
            'aerial': 'car'
        };
        return profileMap[transportType] || 'car';
    }

    // Al abrir el modal, capturamos los datos y disparamos la creación
    $('#mapModal').on('shown.bs.modal', function (event) {
        initMap();
        
        // El truco vital para que el mapa no salga gris dentro de un modal
        setTimeout(() => { map.invalidateSize(); }, 300);

        const button = $(event.relatedTarget); 
        const coordenadasOriginales = button.data('coords');
        const transportType = button.data('transport') || 'car';

        if (coordenadasOriginales && coordenadasOriginales.length > 0) {
            crearRutaOptimizada(coordenadasOriginales, transportType);
        }
    });

    // --- 4. CREACIÓN DE LA RUTA MEDIANTE OSRM ---

    async function crearRutaOptimizada(puntosBrutos, transportType) {
        // Primero: Limpiamos los puntos para que OSRM no haga zig-zag
        const puntosLimpios = limpiarRuta(puntosBrutos);

        // Segundo: Formateamos para la API (longitud,latitud;)
        const coordsString = puntosLimpios.map(p => `${p[0]},${p[1]}`).join(';');

        const profile = osrmProfile(transportType || 'car');
        const url = `https://router.project-osrm.org/route/v1/${profile}/${coordsString}?overview=full&geometries=geojson`;

        try {
            const response = await fetch(url);
            const data = await response.json();

            if (data.code === 'Ok') {
                const rutaFinal = data.routes[0];

                // Limpiamos capas previas
                if (routeLayer) map.removeLayer(routeLayer);

                // Dibujamos la nueva ruta creada sobre el mapa
                routeLayer = L.geoJSON(rutaFinal.geometry, {
                    style: { color: '#00b894', weight: 6, opacity: 0.8 }
                }).addTo(map);

                // Ajustamos el zoom automáticamente
                map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

                // Actualizamos los campos de texto del formulario con los nuevos datos reales
                const distanciaKM = (rutaFinal.distance / 1000).toFixed(2);
                $('#input_distancia_nueva').val(distanciaKM); 
                $('#label_info').text(`Nueva ruta creada de ${distanciaKM} km`);
                
                console.log("Ruta creada exitosamente a partir de los puntos originales.");
            } else {
                alert("OSRM no pudo trazar una ruta lógica entre estos puntos.");
            }
        } catch (error) {
            console.error("Error en la creación de ruta:", error);
        }
    }

    // Al cerrar el modal, reseteamos el estado visual
    $('#mapModal').on('hidden.bs.modal', function () {
        if (routeLayer) map.removeLayer(routeLayer);
    });
});