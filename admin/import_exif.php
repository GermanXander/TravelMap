<?php
/**
 * Importar Puntos desde Fotos (EXIF)
 *
 * Permite crear puntos de interés extrayendo automáticamente
 * las coordenadas GPS y la fecha de imágenes JPEG.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Point.php';

$tripModel  = new Trip();
$trips      = $tripModel->getAll('start_date DESC');
$pointTypes = Point::getTypes();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ===== Page header ===== -->
<div class="page-header mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="page-title mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                     class="me-2" style="vertical-align:-4px">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/>
                    <path d="M9.5 8.5H14.5M9.5 8.5C9.5 8.5 8 8.5 8 10C8 11.5 9.5 11.5 9.5 11.5H14.5C14.5 11.5 16 11.5 16 13C16 14.5 14.5 14.5 14.5 14.5H9.5M12 6.5V8.5M12 14.5V16.5"/>
                </svg>
                <?= __('import_exif.title') ?? 'Importar desde Fotos (EXIF)' ?>
            </h1>
            <p class="text-muted mb-0"><?= __('import_exif.subtitle') ?? 'Crea puntos de interés extrayendo coordenadas GPS y fecha de imágenes JPEG' ?></p>
        </div>
    </div>
</div>

<!-- ===== Alert container ===== -->
<div id="alertContainer" class="mb-3"></div>

<!-- ===================================================================== -->
<!-- STEP 1 — Trip selection + file upload                                  -->
<!-- ===================================================================== -->
<div class="card mb-4" id="step1Card">
    <div class="card-header">
        <h5 class="mb-0">
            <span class="badge bg-primary me-2">1</span>
            <?= __('import_exif.step1_title') ?? 'Seleccionar viaje y subir fotos' ?>
        </h5>
    </div>
    <div class="card-body">

        <?php if (empty($trips)): ?>
            <div class="alert alert-warning">
                <?= __('import_exif.no_trips') ?? 'No hay viajes creados. <a href="trip_form.php">Crea un viaje</a> primero.' ?>
            </div>
        <?php else: ?>

        <div class="row g-3">
            <!-- Trip selector -->
            <div class="col-12 col-md-6">
                <label for="tripSelect" class="form-label fw-semibold">
                    <?= __('import_exif.select_trip') ?? 'Viaje destino' ?> <span class="text-danger">*</span>
                </label>
                <select id="tripSelect" class="form-select">
                    <option value=""><?= __('import_exif.choose_trip') ?? '— Seleccionar viaje —' ?></option>
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

            <!-- File upload area -->
            <div class="col-12">
                <label class="form-label fw-semibold">
                    <?= __('import_exif.photos') ?? 'Fotografías' ?> <span class="text-danger">*</span>
                </label>
                <div id="dropArea" class="exif-drop-area border border-2 border-dashed rounded-3 p-4 text-center"
                     style="cursor:pointer;border-color:#ced4da!important;transition:border-color .2s,background .2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
                         stroke="#adb5bd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2">
                        <path d="M12 16L12 8M12 8L9 11M12 8L15 11"/>
                        <path d="M20 16.7428C21.2215 15.734 22 14.2195 22 12.5C22 9.46243 19.5376 7 16.5 7C16.2815 7 16.0771 6.886 15.9661 6.69788C14.6621 4.48786 12.2544 3 9.5 3C5.35786 3 2 6.35786 2 10.5C2 12.5661 2.83545 14.4371 4.18 15.8"/>
                        <path d="M8 17H16"/>
                        <path d="M8 20H16"/>
                    </svg>
                    <p class="mb-1 fw-semibold"><?= __('import_exif.drop_here') ?? 'Arrastra las fotos aquí o haz clic para explorar' ?></p>
                    <p class="text-muted small mb-2"><?= __('import_exif.only_jpeg') ?? 'Solo imágenes JPEG (requerido para datos EXIF GPS)' ?></p>
                    <input type="file" id="fileInput" multiple accept="image/jpeg,image/jpg" class="d-none">
                    <p id="fileCount" class="mb-0 small text-muted"><?= __('import_exif.no_files') ?? 'Ninguna imagen seleccionada' ?></p>
                </div>
            </div>

            <!-- Upload button -->
            <div class="col-12">
                <button type="button" id="uploadBtn" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/>
                        <path d="M17 8L12 3L7 8"/>
                        <path d="M12 3V15"/>
                    </svg>
                    <?= __('import_exif.process_btn') ?? 'Procesar imágenes' ?>
                </button>
                <span class="text-muted small ms-2"><?= __('import_exif.upload_hint') ?? 'Se leerán los datos EXIF de cada imagen' ?></span>
            </div>

            <!-- Progress bar (hidden until upload starts) -->
            <div id="uploadProgressContainer" class="col-12 d-none">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= __('import_exif.uploading') ?? 'Procesando imágenes...' ?></span>
                    <span id="uploadProgressLabel">0%</span>
                </div>
                <div class="progress" style="height:10px;">
                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- ===================================================================== -->
<!-- STEP 2 — Review and import                                             -->
<!-- ===================================================================== -->
<div id="step2Container" class="d-none">

    <!-- Summary bar -->
    <div class="card mb-3 border-0 bg-light">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="fw-semibold"><?= __('import_exif.step2_title') ?? 'Revisar e importar' ?></span>
                &nbsp;·&nbsp;
                <span id="summaryText" class="text-muted small"></span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="importAllBtn" class="btn btn-success btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <path d="M20 6L9 17L4 12"/>
                    </svg>
                    <?= __('import_exif.import_all_btn') ?? 'Importar habilitados' ?>
                </button>
                <button type="button" id="startOverBtn" class="btn btn-outline-secondary btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C14.8273 3 17.35 4.30367 19 6.37713"/>
                        <path d="M19 3V7H15"/>
                    </svg>
                    <?= __('import_exif.start_over_btn') ?? 'Nueva importación' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Progress bar (hidden until import starts) -->
    <div id="progressContainer" class="mb-3 d-none">
        <div class="d-flex justify-content-between small mb-1">
            <span><?= __('import_exif.importing') ?? 'Importando...' ?></span>
            <span id="progressLabel">0 / 0</span>
        </div>
        <div class="progress" style="height:10px;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <!-- Finish button (hidden until import ends) -->
    <div id="finishContainer" class="mb-3 d-none">
        <button type="button" id="finishBtn" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                <path d="M20 6L9 17L4 12"/>
            </svg>
            <?= __('import_exif.finish_btn') ?? 'Finalizar y limpiar temporales' ?>
        </button>
        <a href="points.php" class="btn btn-outline-secondary ms-2">
            <?= __('import_exif.view_points') ?? 'Ver puntos de interés' ?>
        </a>
    </div>

    <!-- Image cards container -->
    <div id="imagesContainer"></div>

</div>

<!-- ===================================================================== -->
<!-- Inline styles for the drop area                                        -->
<!-- ===================================================================== -->
<style>
.exif-drop-area.drag-over {
    border-color: #0d6efd !important;
    background: #e9f2ff;
}
.exif-thumb {
    width: 110px;
    height: 88px;
    object-fit: cover;
    border-radius: 6px;
    image-orientation: from-image;
}
.exif-card .card-body { padding: 0.85rem 1rem; }
.exif-status { min-width: 90px; text-align: center; }
.exif-card.missing-title {
    border-left: 4px solid #dc3545 !important;
    background: #fff5f5;
}
</style>

<!-- ===================================================================== -->
<!-- JavaScript                                                              -->
<!-- ===================================================================== -->
<script>
(function () {
    'use strict';

    /* ---- PHP → JS data ---- */
    const BASE_URL   = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
    const POINT_TYPES = <?= json_encode($pointTypes) ?>;

    const API_UPLOAD   = BASE_URL + '/api/import_exif_upload.php';
    const API_SAVE     = BASE_URL + '/api/import_exif_save_point.php';
    const API_GEOCODE  = BASE_URL + '/api/reverse_geocode.php';

    let currentToken  = null;
    let currentTripId = null;

    /* ================================================================== */
    /* Utilities                                                            */
    /* ================================================================== */

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(msg, type) {
        const container = document.getElementById('alertContainer');
        const div = document.createElement('div');
        div.className = 'alert alert-' + type + ' alert-dismissible fade show';
        div.innerHTML = esc(msg) +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        container.appendChild(div);
        setTimeout(function () { div.remove(); }, 9000);
    }

    /* ================================================================== */
    /* Phase 1 — File selection & upload                                   */
    /* ================================================================== */

    const dropArea  = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const fileCount = document.getElementById('fileCount');

    dropArea.addEventListener('click', function () { fileInput.click(); });
    dropArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropArea.classList.add('drag-over');
    });
    dropArea.addEventListener('dragleave', function () {
        dropArea.classList.remove('drag-over');
    });
    dropArea.addEventListener('drop', function (e) {
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
            ? n + ' imagen' + (n > 1 ? 'es' : '') + ' seleccionada' + (n > 1 ? 's' : '')
            : 'Ninguna imagen seleccionada';
    }

    document.getElementById('uploadBtn').addEventListener('click', uploadImages);

    async function uploadImages() {
        const tripId = document.getElementById('tripSelect').value;
        if (!tripId) {
            showAlert('Por favor seleccione un viaje de destino.', 'warning');
            return;
        }
        if (!fileInput.files || fileInput.files.length === 0) {
            showAlert('Por favor seleccione al menos una imagen JPEG.', 'warning');
            return;
        }

        const btn = document.getElementById('uploadBtn');
        btn.disabled = true;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Procesando...';

        currentTripId = tripId;

        const formData = new FormData();
        for (const file of fileInput.files) {
            formData.append('images[]', file);
        }

        // Show progress container
        const progressContainer = document.getElementById('uploadProgressContainer');
        progressContainer.classList.remove('d-none');
        
        const totalImages = fileInput.files.length;
        // Initialize progress to 0 / totalImages (not 0%)
        updateUploadProgress(0, totalImages);

        try {
            await new Promise(function (resolve, reject) {
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        // Estimar cantidad de imágenes procesadas basado en el porcentaje
                        const estimatedImages = Math.max(1, Math.ceil((percentComplete / 100) * totalImages));
                        updateUploadProgress(estimatedImages, totalImages);
                    }
                });
                
                xhr.addEventListener('load', function () {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            currentToken = data.token;

                            if (data.errors && data.errors.length) {
                                showAlert(
                                    'Algunos archivos no pudieron procesarse: ' + data.errors.join(' | '),
                                    'warning'
                                );
                            }

                            if (!data.images || data.images.length === 0) {
                                showAlert('No se procesaron imágenes válidas. Verifica que los archivos sean JPEG.', 'danger');
                                reject(new Error('No valid images'));
                            } else {
                                renderStep2(data.images);
                                resolve();
                            }
                        } else {
                            showAlert(data.error || 'Error al procesar las imágenes.', 'danger');
                            reject(new Error(data.error));
                        }
                    } catch (e) {
                        showAlert('Error al procesar respuesta del servidor.', 'danger');
                        reject(e);
                    }
                });
                
                xhr.addEventListener('error', function () {
                    showAlert('Error de comunicación con el servidor.', 'danger');
                    reject(new Error('Network error'));
                });
                
                xhr.addEventListener('abort', function () {
                    showAlert('Carga cancelada por el usuario.', 'warning');
                    reject(new Error('User abort'));
                });
                
                xhr.open('POST', API_UPLOAD);
                xhr.send(formData);
            });
        } catch (err) {
            // Error already shown in promise handlers
        } finally {
            btn.disabled = false;
            btn.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/><path d="M17 8L12 3L7 8"/><path d="M12 3V15"/></svg> Procesar imágenes';
            
            // Hide progress container after completion
            setTimeout(function () {
                progressContainer.classList.add('d-none');
                updateUploadProgress(0, 0);
            }, 500);
        }
    }

    function updateUploadProgress(current, total) {
        const bar = document.getElementById('uploadProgressBar');
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);
        
        const label = document.getElementById('uploadProgressLabel');
        if (total > 0) {
            label.textContent = current + ' / ' + total + ' imágenes';
        } else {
            label.textContent = '0%';
        }
    }

    /* ================================================================== */
    /* Phase 2 — Render review cards                                       */
    /* ================================================================== */

    function renderStep2(images) {
        document.getElementById('step1Card').classList.add('d-none');
        document.getElementById('step2Container').classList.remove('d-none');

        const withGps      = images.filter(function (i) { return i.has_gps && !i.gps_estimated; }).length;
        const withEstimated = images.filter(function (i) { return i.gps_estimated; }).length;
        const ready        = images.filter(function (i) { return i.has_date; }).length;
        
        let summaryStr = images.length + ' imagen(es) · ' + withGps + ' con GPS';
        if (withEstimated > 0) {
            summaryStr += ' · ' + withEstimated + ' estimadas';
        }
        summaryStr += ' · ' + ready + ' listas para importar';
        
        document.getElementById('summaryText').textContent = summaryStr;

        const container = document.getElementById('imagesContainer');
        container.innerHTML = '';
        images.forEach(function (img, index) {
            container.appendChild(createImageCard(img, index));
        });
    }

    /* ---- Build type <options> ---- */
    function buildTypeOptions(selected) {
        return Object.entries(POINT_TYPES).map(function ([key, label]) {
            return '<option value="' + esc(key) + '"' + (key === selected ? ' selected' : '') + '>' + esc(label) + '</option>';
        }).join('');
    }

    /* ---- Geocode button SVG ---- */
    const GLOBE_SVG =
        '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<circle cx="12" cy="12" r="10"/>' +
        '<line x1="2" y1="12" x2="22" y2="12"/>' +
        '<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>' +
        '</svg>';

    function createImageCard(img, index) {
        const hasGps  = img.has_gps  === true;
        const hasDate = img.has_date === true;
        const gpsEstimated = img.gps_estimated === true;
        const enabled = hasDate;  // Solo requiere fecha (puede venir del EXIF o del nombre de archivo)

        const lat  = hasGps  ? Number(img.latitude).toFixed(6)  : '';
        const lng  = hasGps  ? Number(img.longitude).toFixed(6) : '';
        const date = hasDate ? img.date : '';

        /* Status badges */
        let badgeHtml = '';
        if (!hasGps && !hasDate) {
            badgeHtml = '<span class="badge bg-secondary mt-1" title="Sin coordenadas GPS ni fecha EXIF">Sin GPS · Sin fecha</span>';
        } else if (!hasDate) {
            badgeHtml = '<span class="badge bg-secondary mt-1" title="No se encontró fecha en el EXIF ni en el nombre del archivo">Sin fecha</span>';
        } else if (gpsEstimated) {
            badgeHtml = '<span class="badge bg-warning text-dark mt-1" title="coordenadas estimadas por interpolación de imágenes anterior y posterior">📍 GPS estimado</span>';
        } else if (!hasGps) {
            badgeHtml = '<span class="badge bg-info text-dark mt-1" title="Fecha presente pero sin coordenadas GPS">Fecha ✓</span>';
        } else {
            badgeHtml = '<span class="badge bg-success mt-1">GPS + Fecha ✓</span>';
        }

        const card = document.createElement('div');
        card.className = 'card mb-3 exif-card';
        card.dataset.tempFilename = img.temp_filename;
        card.dataset.status       = 'pending';

        card.innerHTML = [
            '<div class="card-body">',
            '  <div class="row g-3 align-items-start">',

            /* --- Col 1: checkbox + thumbnail + filename --- */
            '    <div class="col-auto d-flex flex-column align-items-center gap-1" style="min-width:54px">',
            '      <input type="checkbox" class="form-check-input exif-include-check" id="chk_' + index + '"',
            '             style="width:1.3em;height:1.3em;cursor:pointer;"',
                         (enabled ? ' checked' : '') + '>',
            '      <label for="chk_' + index + '" class="visually-hidden">Incluir imagen ' + (index + 1) + '</label>',
            '      <div style="width:110px;height:88px;overflow:hidden;border-radius:6px;background:#e9ecef;flex-shrink:0;">',
            '        <img src="' + esc(img.url) + '" alt="' + esc(img.original_name) + '" class="exif-thumb"',
            '             loading="lazy" width="110" height="88">',
            '      </div>',
            '      <small class="text-muted text-center lh-1" style="font-size:.67em;max-width:110px;word-break:break-all;">' + esc(img.original_name) + '</small>',
            '      ' + badgeHtml,
            '    </div>',

            /* --- Col 2: form fields --- */
            '    <div class="col">',
            '      <div class="row g-2">',

            /* Point name + geocode */
            '        <div class="col-12 col-lg-7">',
            '          <label class="form-label small mb-1 fw-semibold">Nombre del punto <span class="text-danger">*</span></label>',
            '          <div class="input-group input-group-sm">',
            '            <input type="text" class="form-control exif-field-title"',
            '                   placeholder="Ej: Buenos Aires" required>',
            (hasGps
                ? '            <button type="button" class="btn btn-outline-secondary btn-geocode"' +
                  '                    data-lat="' + lat + '" data-lng="' + lng + '"' +
                  '                    title="Auto-completar nombre de ciudad desde coordenadas GPS">' +
                  GLOBE_SVG + ' Ciudad</button>'
                : ''),
            '          </div>',
            '        </div>',

            /* Type */
            '        <div class="col-12 col-lg-5">',
            '          <label class="form-label small mb-1 fw-semibold">Tipo</label>',
            '          <select class="form-select form-select-sm exif-field-type">',
            '            ' + buildTypeOptions('visit'),
            '          </select>',
            '        </div>',

            /* Date */
            '        <div class="col-12 col-sm-4">',
            '          <label class="form-label small mb-1 fw-semibold">Fecha y Hora de visita</label>',
            '          <input type="datetime-local" class="form-control form-control-sm exif-field-date" value="' + esc(date.includes('T') ? date : date + 'T12:00') + '">',
            '        </div>',

            /* Lat */
            '        <div class="col-12 col-sm-4">',
            '          <label class="form-label small mb-1 fw-semibold">Latitud</label>',
            '          <input type="number" class="form-control form-control-sm exif-field-lat"',
            '                 step="0.000001" min="-90" max="90" value="' + esc(lat) + '"',
            '                 placeholder="Ej: -34.603722">',
            '        </div>',

            /* Lng */
            '        <div class="col-12 col-sm-4">',
            '          <label class="form-label small mb-1 fw-semibold">Longitud</label>',
            '          <input type="number" class="form-control form-control-sm exif-field-lng"',
            '                 step="0.000001" min="-180" max="180" value="' + esc(lng) + '"',
            '                 placeholder="Ej: -58.381592">',
            '        </div>',

            /* Description */
            '        <div class="col-12">',
            '          <label class="form-label small mb-1 fw-semibold">Descripción <span class="text-muted fw-normal">(opcional)</span></label>',
            '          <textarea class="form-control form-control-sm exif-field-desc" rows="2"',
            '                    placeholder="Descripción del lugar..."></textarea>',
            '        </div>',
            '      </div>',
            '    </div>',

            /* --- Col 3: status --- */
            '    <div class="col-auto d-flex align-items-center justify-content-end">',
            '      <div class="exif-status">',
            '        <span class="badge bg-secondary status-pending d-block">Pendiente</span>',
            '        <span class="badge bg-primary status-saving d-none">',
            '          <span class="spinner-border spinner-border-sm" style="width:.75em;height:.75em;"></span>',
            '          &nbsp;Guardando',
            '        </span>',
            '        <span class="badge bg-success status-saved d-none">✓&nbsp;Guardado</span>',
            '        <span class="badge bg-danger status-error d-none" title=""></span>',
            '      </div>',
            '    </div>',

            '  </div>',
            '</div>',
        ].join('\n');

        /* Geocode button handler AND auto-geocode */
        const titleInput = card.querySelector('.exif-field-title');
        
        async function performGeocoding(lat, lng, isAutomatic = false) {
            const geocodeBtn = card.querySelector('.btn-geocode');
            if (geocodeBtn && !isAutomatic) {
                geocodeBtn.disabled = true;
                geocodeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75em;height:.75em;"></span>';
            }
            
            // Show spinner on title field while geocoding
            const titleInput = card.querySelector('.exif-field-title');
            if (isAutomatic) {
                titleInput.parentElement.classList.add('position-relative');
                let spinner = titleInput.parentElement.querySelector('.geocode-spinner');
                if (!spinner) {
                    spinner = document.createElement('div');
                    spinner.className = 'geocode-spinner';
                    spinner.innerHTML = '<span class="spinner-border spinner-border-sm position-absolute" style="right:10px;top:50%;transform:translateY(-50%);width:1.2rem;height:1.2rem;\"></span>';
                    spinner.style.position = 'absolute';
                    spinner.style.right = '10px';
                    spinner.style.top = '50%';
                    spinner.style.transform = 'translateY(-50%)';
                    titleInput.parentElement.appendChild(spinner);
                }
            }

            try {
                const r = await fetch(
                    API_GEOCODE + '?lat=' + encodeURIComponent(lat) +
                    '&lng=' + encodeURIComponent(lng)
                );
                const gd = await r.json();
                if (gd.success && gd.city) {
                    titleInput.value = gd.city;
                    if (!isAutomatic) titleInput.focus();
                } else if (!isAutomatic) {
                    showAlert('No se pudo obtener el nombre de la ciudad: ' + (gd.error || ''), 'warning');
                }
            } catch (e) {
                if (!isAutomatic) {
                    showAlert('Error al geocodificar: ' + e.message, 'warning');
                }
            } finally {
                if (geocodeBtn && !isAutomatic) {
                    geocodeBtn.disabled = false;
                    geocodeBtn.innerHTML = GLOBE_SVG + ' Ciudad';
                }
                // Remove spinner if automatic
                if (isAutomatic) {
                    const spinner = titleInput.parentElement.querySelector('.geocode-spinner');
                    if (spinner) spinner.remove();
                }
            }
        }
        
        // Auto-geocode if GPS is available
        if (hasGps && lat && lng) {
            performGeocoding(lat, lng, true);
        }
        
        // Manual geocode button handler
        const geocodeBtn = card.querySelector('.btn-geocode');
        if (geocodeBtn) {
            geocodeBtn.addEventListener('click', async function () {
                await performGeocoding(this.dataset.lat, this.dataset.lng, false);
            });
        }

        return card;
    }

    /* ================================================================== */
    /* Phase 3 — Import                                                    */
    /* ================================================================== */

    document.getElementById('importAllBtn').addEventListener('click', importAll);

    async function importAll() {
        const allCards = Array.from(document.querySelectorAll('.exif-card'));
        const enabled  = allCards.filter(function (c) {
            const cb = c.querySelector('.exif-include-check');
            return cb && cb.checked && c.dataset.status === 'pending';
        });

        if (enabled.length === 0) {
            showAlert('No hay imágenes habilitadas y pendientes para importar.', 'warning');
            return;
        }

        /* Validate required fields before starting */
        let firstMissingTitle = null;
        let missingCount = 0;
        
        for (const card of enabled) {
            const title = card.querySelector('.exif-field-title').value.trim();
            const lat   = card.querySelector('.exif-field-lat').value.trim();
            const lng   = card.querySelector('.exif-field-lng').value.trim();

            if (!title) {
                missingCount++;
                if (!firstMissingTitle) {
                    firstMissingTitle = card;
                }
                // Highlight card without title
                card.classList.add('missing-title');
                card.querySelector('.exif-field-title').classList.add('is-invalid');
            } else {
                // Remove highlight if it exists
                card.classList.remove('missing-title');
                card.querySelector('.exif-field-title').classList.remove('is-invalid');
            }
            
            if (!lat || !lng) {
                showAlert('Todos los puntos habilitados deben tener coordenadas (latitud y longitud).', 'warning');
                return;
            }
        }

        if (missingCount > 0) {
            const msg = missingCount === 1 
                ? 'Falta un nombre para 1 imagen. Completa el campo resaltado en rojo.'
                : 'Faltan nombres para ' + missingCount + ' imágenes. Completa los campos resaltados en rojo.';
            showAlert(msg, 'danger');
            if (firstMissingTitle) {
                firstMissingTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstMissingTitle.querySelector('.exif-field-title').focus();
            }
            return;
        }

        /* Lock UI */
        document.getElementById('importAllBtn').disabled = true;
        document.getElementById('startOverBtn').disabled = true;

        const progressContainer = document.getElementById('progressContainer');
        progressContainer.classList.remove('d-none');

        const total = enabled.length;
        let done    = 0;
        let errors  = 0;

        updateProgress(0, total);

        for (const card of enabled) {
            setCardStatus(card, 'saving');

            const payload = {
                trip_id:       currentTripId,
                title:         card.querySelector('.exif-field-title').value.trim(),
                description:   card.querySelector('.exif-field-desc').value.trim(),
                type:          card.querySelector('.exif-field-type').value,
                visit_date:    card.querySelector('.exif-field-date').value || null,
                latitude:      parseFloat(card.querySelector('.exif-field-lat').value),
                longitude:     parseFloat(card.querySelector('.exif-field-lng').value),
                temp_token:    currentToken,
                temp_filename: card.dataset.tempFilename,
            };

            try {
                const r      = await fetch(API_SAVE, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload),
                });
                const result = await r.json();

                if (result.success) {
                    setCardStatus(card, 'saved');
                    card.dataset.status = 'saved';
                } else {
                    setCardStatus(card, 'error', result.error || 'Error desconocido');
                    card.dataset.status = 'error';
                    errors++;
                }
            } catch (e) {
                setCardStatus(card, 'error', e.message);
                card.dataset.status = 'error';
                errors++;
            }

            done++;
            updateProgress(done, total);
        }

        /* Show result */
        if (errors === 0) {
            showAlert(done + ' punto(s) importado(s) correctamente.', 'success');
        } else {
            showAlert((done - errors) + ' importado(s), ' + errors + ' con error(es). Revisa los marcados en rojo.', 'warning');
        }

        document.getElementById('importAllBtn').classList.add('d-none');
        document.getElementById('finishContainer').classList.remove('d-none');
    }

    /* ---- Status helpers ---- */

    function setCardStatus(card, status, errorMsg) {
        ['pending', 'saving', 'saved', 'error'].forEach(function (s) {
            card.querySelector('.status-' + s).classList.add('d-none');
        });
        card.querySelector('.status-' + status).classList.remove('d-none');

        if (status === 'error') {
            const badge = card.querySelector('.status-error');
            badge.textContent = '✗ Error';
            if (errorMsg) {
                badge.title = errorMsg;
            }
        }

        /* Freeze inputs while saving */
        if (status === 'saving') {
            card.querySelectorAll('input, select, textarea, button').forEach(function (el) {
                el.disabled = true;
            });
        }
    }

    function updateProgress(done, total) {
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        const bar  = document.getElementById('progressBar');
        bar.style.width            = pct + '%';
        bar.setAttribute('aria-valuenow', pct);
        document.getElementById('progressLabel').textContent = done + ' / ' + total;
    }

    /* ================================================================== */
    /* Cleanup & reset                                                     */
    /* ================================================================== */

    document.getElementById('finishBtn').addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Limpiando...';
        await cleanup();
        resetToStep1();
    });

    document.getElementById('startOverBtn').addEventListener('click', async function () {
        if (!confirm('¿Desea cancelar la importación actual? Las imágenes temporales no guardadas serán eliminadas.')) {
            return;
        }
        await cleanup();
        resetToStep1();
    });

    async function cleanup() {
        if (!currentToken) return;
        try {
            await fetch(API_SAVE, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'cleanup', temp_token: currentToken }),
            });
        } catch (e) {
            console.warn('Cleanup failed:', e);
        }
        currentToken = null;
    }

    function resetToStep1() {
        /* Show step 1, hide step 2 */
        document.getElementById('step1Card').classList.remove('d-none');
        document.getElementById('step2Container').classList.add('d-none');

        /* Reset step 2 internals */
        document.getElementById('imagesContainer').innerHTML = '';
        document.getElementById('progressContainer').classList.add('d-none');
        document.getElementById('finishContainer').classList.add('d-none');
        document.getElementById('progressBar').style.width = '0%';
        document.getElementById('importAllBtn').disabled = false;
        document.getElementById('importAllBtn').classList.remove('d-none');
        document.getElementById('startOverBtn').disabled = false;

        /* Reset step 1 form */
        fileInput.value = '';
        updateFileCount();
        currentTripId = null;
    }

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
