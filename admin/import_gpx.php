<?php
/**
 * Importar Rutas desde GPX (GraphHopper)
 *
 * Permite importar rutas desde archivos GPX exportados de GraphHopper u otras fuentes.
 * Los <wpt> se guardan como puntos de interés y los <trkpt> como ruta LineString.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/Route.php';

$tripModel = new Trip();
$trips = $tripModel->getAll('start_date DESC');
$transportTypes = [
    'plane' => 'Avión',
    'car' => 'Auto',
    'bike' => 'Bicicleta',
    'walk' => 'Caminata',
    'ship' => 'Barco',
    'train' => 'Tren',
    'bus' => 'Bus',
    'aerial' => 'Aéreo'
];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_choice = $_POST['trip_choice'] ?? 'new';
    $existing_trip_id = $_POST['existing_trip_id'] ?? null;
    $new_trip_name = trim($_POST['new_trip_name'] ?? '');
    $transport_type = $_POST['transport_type'] ?? 'car';

    if ($trip_choice === 'existing' && empty($existing_trip_id)) {
        $error = __('import_gpx.error_no_trip');
    } elseif ($trip_choice === 'new' && empty($new_trip_name)) {
        $error = __('import_gpx.error_no_trip');
    } elseif (!isset($_FILES['gpx_file']) || $_FILES['gpx_file']['error'] !== UPLOAD_ERR_OK) {
        $error = __('import_gpx.error_no_file');
    } else {
        $file = $_FILES['gpx_file'];
        $tmp_name = $file['tmp_name'];

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'gpx') {
            $error = __('import_gpx.error_invalid_file');
        } else {
            $xml = simplexml_load_file($tmp_name);
            if (!$xml) {
                $error = __('import_gpx.error_invalid_gpx');
            } else {
                $trip_id = null;
                $trip_name = '';

                if ($trip_choice === 'existing') {
                    $trip_id = (int)$existing_trip_id;
                    $trip = $tripModel->getById($trip_id);
                    $trip_name = $trip['title'] ?? 'Viaje existente';
                } else {
                    $tripModel = new Trip();
                    $trip_id = $tripModel->create([
                        'title'       => $new_trip_name,
                        'description' => 'Importado desde GPX (GraphHopper)',
                        'start_date'  => date('Y-m-d'),
                        'end_date'    => date('Y-m-d'),
                        'color_hex'   => Route::getColorByTransport($transport_type),
                        'status'      => 'draft'
                    ]);
                    $trip_name = $new_trip_name;
                }

                if (!$trip_id) {
                    $error = __('import_gpx.error_trip');
                } else {
                    $pointModel = new Point();
                    $routeModel = new Route();

                    $wpt_count = 0;
                    $trk_count = 0;

                    if (isset($xml->wpt)) {
                        foreach ($xml->wpt as $wpt) {
                            $lat = (float)$wpt['lat'];
                            $lon = (float)$wpt['lon'];
                            $name = (string)($wpt->name ?? 'Punto importado ' . ($wpt_count + 1));
                            $desc = (string)($wpt->desc ?? '');

                            $result = $pointModel->create([
                                'trip_id'     => $trip_id,
                                'title'       => $name,
                                'description' => $desc,
                                'type'        => 'waypoint',
                                'latitude'    => $lat,
                                'longitude'   => $lon
                            ]);

                            if ($result) $wpt_count++;
                        }
                    }

                    $coordinates = [];
                    if (isset($xml->trk->trkseg->trkpt)) {
                        foreach ($xml->trk->trkseg->trkpt as $trkpt) {
                            $lat = (float)$trkpt['lat'];
                            $lon = (float)$trkpt['lon'];
                            $coordinates[] = [$lon, $lat];
                        }
                    }

                    if (count($coordinates) >= 2) {
                        $geojson = json_encode([
                            "type" => "Feature",
                            "properties" => [],
                            "geometry" => [
                                "type" => "LineString",
                                "coordinates" => $coordinates
                            ]
                        ]);

                        $result = $routeModel->create([
                            'trip_id'        => $trip_id,
                            'name'           => 'Ruta importada',
                            'transport_type' => $transport_type,
                            'geojson_data'   => $geojson,
                            'color'          => Route::getColorByTransport($transport_type),
                            'is_round_trip'  => 0
                        ]);

                        if ($result) $trk_count = count($coordinates);
                    }

                    $success = __('import_gpx.success') . '<br>' .
                               '<strong>' . __('import_gpx.success_trip') . ':</strong> ' . htmlspecialchars($trip_name) . '<br>' .
                               '<strong>' . __('import_gpx.success_points') . ':</strong> ' . $wpt_count . '<br>' .
                               '<strong>' . __('import_gpx.success_route_points') . ':</strong> ' . $trk_count;
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="me-2" style="vertical-align:-4px">
                    <path d="M5.5 3C4.11929 3 3 4.11929 3 5.5V8.5C3 9.88071 4.11929 11 5.5 11H8.5C9.88071 11 11 9.88071 11 8.5V5.5C11 4.11929 9.88071 3 8.5 3H5.5Z"/>
                    <path d="M8.5 13H5.5C4.11929 13 3 14.1193 3 15.5V18.5C3 19.8807 4.11929 21 5.5 21H8.5C9.88071 21 11 19.8807 11 18.5V15.5C11 14.1193 9.88071 13 8.5 13Z"/>
                    <path d="M18.5 3H15.5C14.1193 3 13 4.11929 13 5.5V8.5C13 9.88071 14.1193 11 15.5 11H18.5C19.8807 11 21 9.88071 21 8.5V5.5C21 4.11929 19.8807 3 18.5 3Z"/>
                    <path d="M15.5 13H18.5C19.8807 13 21 14.1193 21 15.5V18.5C21 19.8807 19.8807 21 18.5 21H15.5C14.1193 21 13 19.8807 13 18.5V15.5C13 14.1193 14.1193 13 15.5 13Z"/>
                    <path d="M12 8L12 16"/>
                </svg>
                <?= __('import_gpx.title') ?>
            </h1>
            <p class="text-muted mb-0"><?= __('import_gpx.subtitle') ?></p>
        </div>
    </div>
</div>


<div id="alertContainer" class="mb-3"></div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/>
                <path d="M17 8L12 3L7 8"/>
                <path d="M12 3V15"/>
            </svg>
            <?= __('import_gpx.step1_title') ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="importForm">
            <div class="row g-3">
                <!-- Viaje destino -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">
                        <?= __('import_gpx.select_trip') ?> <span class="text-danger">*</span>
                    </label>
                    <div class="mb-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="trip_choice" id="tripChoiceNew" value="new" checked>
                            <label class="form-check-label" for="tripChoiceNew"><?= __('import_gpx.create_new_trip') ?></label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="trip_choice" id="tripChoiceExisting" value="existing">
                            <label class="form-check-label" for="tripChoiceExisting"><?= __('import_gpx.add_to_existing') ?></label>
                        </div>
                    </div>
                    <div id="newTripFields">
                        <input type="text" class="form-control" id="new_trip_name" name="new_trip_name" placeholder="<?= __('import_gpx.trip_name_placeholder') ?>">
                    </div>
                    <div id="existingTripFields" class="d-none">
                        <select class="form-select" id="existing_trip_id" name="existing_trip_id">
                            <option value=""><?= __('import_gpx.choose_trip') ?></option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= (int)$trip['id'] ?>">
                                    <?= htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($trip['start_date']): ?>
                                        (<?= date('d/m/Y', strtotime($trip['start_date'])) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="w-100"></div>
                <!-- Tipo de transporte -->
                <div class="col-12 col-md-6">
                    <label for="transport_type" class="form-label fw-semibold">
                        <?= __('import_gpx.transport_type') ?> <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="transport_type" name="transport_type">
                        <?php foreach ($transportTypes as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $key === 'car' ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Archivo GPX -->
                <div class="col-12">
                    <label class="form-label fw-semibold">
                        <?= __('import_gpx.gpx_file') ?> <span class="text-danger">*</span>
                    </label>
                    <div id="dropArea" class="drop-area border border-2 border-dashed rounded-3 p-4 text-center"
                         style="cursor:pointer;border-color:#ced4da!important;transition:border-color .2s,background .2s;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
                             stroke="#adb5bd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/>
                            <path d="M2 12H22"/>
                            <path d="M12 2C9.33333 7.33333 9.33333 16.6667 12 22C14.6667 16.6667 14.6667 7.33333 12 2Z"/>
                        </svg>
                        <p class="mb-1 fw-semibold"><?= __('import_gpx.drop_here') ?></p>
                        <p class="text-muted small mb-2"><?= __('import_gpx.only_gpx') ?></p>
                        <input type="file" id="fileInput" name="gpx_file" accept=".gpx" class="d-none">
                        <p id="fileCount" class="mb-0 small text-muted"><?= __('import_gpx.no_files') ?></p>
                    </div>
                </div>

                <!-- Botones -->
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                            <path d="M20 6L9 17L4 12"/>
                        </svg>
                        <?= __('import_gpx.process_btn') ?>
                    </button>
                    <a href="trips.php" class="btn btn-outline-secondary ms-2"><?= __('import_gpx.back_btn') ?? 'Volver a Viajes' ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.drop-area.drag-over {
    border-color: #0d6efd !important;
    background: #e9f2ff;
}
#importForm {
    flex-grow: 0;
}
</style>

<script>
(function() {
    'use strict';

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const fileCount = document.getElementById('fileCount');

    dropArea.addEventListener('click', function() { fileInput.click(); });
    dropArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropArea.classList.add('drag-over');
    });
    dropArea.addEventListener('dragleave', function() {
        dropArea.classList.remove('drag-over');
    });
    dropArea.addEventListener('drop', function(e) {
        e.preventDefault();
        dropArea.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileCount();
        }
    });
    fileInput.addEventListener('change', updateFileCount);

    function updateFileCount() {
        const n = fileInput.files ? fileInput.files.length : 0;
        fileCount.textContent = n > 0 
            ? n + ' archivo' + (n > 1 ? 's' : '') + ' seleccionado' + (n > 1 ? 's' : '')
            : 'Ningún archivo seleccionado';
    }

    const tripChoiceNew = document.getElementById('tripChoiceNew');
    const tripChoiceExisting = document.getElementById('tripChoiceExisting');
    const newTripFields = document.getElementById('newTripFields');
    const existingTripFields = document.getElementById('existingTripFields');
    const newTripName = document.getElementById('new_trip_name');
    const existingTripId = document.getElementById('existing_trip_id');

    tripChoiceNew.addEventListener('change', function() {
        if (this.checked) {
            newTripFields.classList.remove('d-none');
            existingTripFields.classList.add('d-none');
            newTripName.setAttribute('required', 'required');
            existingTripId.removeAttribute('required');
        }
    });

    tripChoiceExisting.addEventListener('change', function() {
        if (this.checked) {
            newTripFields.classList.add('d-none');
            existingTripFields.classList.remove('d-none');
            newTripName.removeAttribute('required');
            existingTripId.setAttribute('required', 'required');
        }
    });

    const transportSelect = document.getElementById('transport_type');
    transportSelect.addEventListener('change', function() {
        const color = getTransportColor(this.value);
        dropArea.style.borderColor = color + ' !important';
    });

    function getTransportColor(type) {
        const colors = {
            'plane': '#FF4444',
            'car': '#4444FF',
            'bike': '#b88907',
            'walk': '#44FF44',
            'ship': '#00AAAA',
            'train': '#FF8800',
            'bus': '#9C27B0',
            'aerial': '#E91E63'
        };
        return colors[type] || '#3388ff';
    }

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
