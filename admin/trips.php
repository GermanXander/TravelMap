<?php
/**
 * Gestión de Viajes
 * 
 * Listado de viajes con opciones para crear, editar y eliminar
 */

// Cargar configuración primero
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/TripTag.php';
require_once __DIR__ . '/../src/models/Settings.php';

$tripModel = new Trip();
$tripTagModel = new TripTag();
$settingsModel = new Settings(getDB());

// Verificar si el sistema de tags está habilitado
$tripTagsEnabled = $settingsModel->get('trip_tags_enabled', true);

$message = '';
$message_type = '';

// Procesar eliminación
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $trip_id = (int) $_GET['delete'];
    if ($tripModel->delete($trip_id)) {
        $message = __('trips.deleted_success');
        $message_type = 'success';
    } else {
        $message = __('trips.error_deleting');
        $message_type = 'danger';
    }
}

// Procesar acción masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $trip_ids = isset($_POST['trip_ids']) ? array_map('intval', $_POST['trip_ids']) : [];
    
    if (!empty($trip_ids)) {
        $success_count = 0;
        
        if ($action === 'publish') {
            foreach ($trip_ids as $id) {
                if ($tripModel->update($id, ['status' => 'published'])) {
                    $success_count++;
                }
            }
            $message = "$success_count " . __('trips.trips_published');
            $message_type = 'success';
        } elseif ($action === 'draft') {
            foreach ($trip_ids as $id) {
                if ($tripModel->update($id, ['status' => 'draft'])) {
                    $success_count++;
                }
            }
            $message = "$success_count " . __('trips.trips_drafted');
            $message_type = 'success';
        } elseif ($action === 'delete') {
            foreach ($trip_ids as $id) {
                if ($tripModel->delete($id)) {
                    $success_count++;
                }
            }
            $message = "$success_count " . __('trips.trips_deleted');
            $message_type = 'success';
        }
    } else {
        $message = __('trips.no_trips_selected');
        $message_type = 'warning';
    }
}

// Ahora incluir header después de procesar
require_once __DIR__ . '/../includes/header.php';

// Obtener todos los viajes
$trips = $tripModel->getAll('start_date DESC, created_at DESC');

// Cargar tags para cada viaje solo si está habilitado
if ($tripTagsEnabled) {
    foreach ($trips as &$trip) {
        $trip['tags'] = $tripTagModel->getByTripId($trip['id']);
    }
    unset($trip); // Romper referencia
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
            </svg>
            <?= __('trips.management') ?>
        </h1>
    </div>
    <div class="page-actions">
        <a href="trip_form.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <?= __('trips.new_trip') ?>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <?php if ($message_type === 'success'): ?>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            <?php else: ?>
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            <?php endif; ?>
        </svg>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($trips)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-6l-2 3h-4l-2-3H2"></path>
                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                </svg>
                <h4 class="empty-state-title"><?= __('trips.no_trips') ?></h4>
                <p class="empty-state-text"><?= __('messages.please_wait') ?></p>
                <a href="trip_form.php" class="btn btn-primary"><?= __('trips.new_trip') ?></a>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <!-- Bulk Action Toolbar -->
                <div class="bulk-toolbar hidden" id="bulkToolbar">
                    <div class="bulk-info">
                        <strong><span id="selectedCount">0</span> <?= __('trips.select_trips') ?? 'selected' ?></strong>
                    </div>
                    <div class="bulk-actions">
                        <button type="submit" name="bulk_action" value="publish" class="btn btn-success btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <?= __('trips.publish') ?>
                        </button>
                        <button type="submit" name="bulk_action" value="draft" class="btn btn-secondary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                            <?= __('trips.draft') ?>
                        </button>
                        <button type="submit" name="bulk_action" value="delete" class="btn btn-danger btn-sm" 
                                onclick="return confirm('<?= __('trips.confirm_delete') ?>')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <?= __('common.delete') ?>
                        </button>
                    </div>
                </div>
                
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll" title="<?= __('common.select_all') ?>">
                                </th>
                                <th style="width: 50px;"><?= __('trips.color') ?></th>
                                <th><?= __('trips.title_field') ?></th>
                                <th style="width: 180px;"><?= __('common.date') ?></th>
                                <th style="width: 100px;"><?= __('trips.status') ?></th>
                                <th style="width: 90px;"><?= __('trips.points') ?></th>
                                <th style="width: 130px;" class="table-actions"><?= __('common.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input trip-select" 
                                               name="trip_ids[]" value="<?= $trip['id'] ?>">
                                    </td>
                                    <td>
                                        <div class="color-swatch" style="background-color: <?= htmlspecialchars($trip['color_hex']) ?>;"></div>
                                    </td>
                                    <td>
                                        <div class="cell-title"><?= htmlspecialchars($trip['title']) ?></div>
                                        <?php if ($trip['description']): ?>
                                            <div class="cell-subtitle"><?= htmlspecialchars(mb_substr($trip['description'], 0, 60)) ?><?= mb_strlen($trip['description']) > 60 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($tripTagsEnabled && !empty($trip['tags'])): ?>
                                            <div class="mt-1">
                                                <?php foreach ($trip['tags'] as $tag): ?>
                                                    <span class="badge bg-light text-dark border me-1" style="font-size: 0.7em;">
                                                        <?= htmlspecialchars($tag) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip['start_date'] && $trip['end_date']): ?>
                                            <div class="cell-date-range">
                                                <span><?= date('d/m/Y', strtotime($trip['start_date'])) ?></span>
                                                <span class="arrow">→</span>
                                                <span><?= date('d/m/Y', strtotime($trip['end_date'])) ?></span>
                                            </div>
                                        <?php elseif ($trip['start_date']): ?>
                                            <span class="cell-date"><?= date('d/m/Y', strtotime($trip['start_date'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip['status'] === 'published'): ?>
                                            <span class="badge badge-success"><?= __('trips.published') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?= __('trips.draft') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= $tripModel->countPoints($trip['id']) ?></span>
                                    </td>
                                    <td class="table-actions">
                                        <div class="btn-group">
                                            <a href="trip_edit_map.php?id=<?= $trip['id'] ?>" class="btn btn-icon btn-sm btn-outline-success" title="<?= __('trips.map_editor') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                                                    <line x1="8" y1="2" x2="8" y2="18"></line>
                                                    <line x1="16" y1="6" x2="16" y2="22"></line>
                                                </svg>
                                            </a>
                                            <a href="trip_form.php?id=<?= $trip['id'] ?>" class="btn btn-icon btn-sm btn-outline-primary" title="<?= __('common.edit') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-info border" title="<?= __('map.create_route_from_poi') ?>" onclick="showCreateRouteFromPOIModal(<?= $trip['id'] ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor" class="bi bi-signpost-split">
                                                    <path d="M7 7V1.414a1 1 0 0 1 2 0V2h5a1 1 0 0 1 .8.4l.975 1.3a.5.5 0 0 1 0 .6L14.8 5.6a1 1 0 0 1-.8.4H9v10H7v-5H2a1 1 0 0 1-.8-.4L.225 9.3a.5.5 0 0 1 0-.6L1.2 7.4A1 1 0 0 1 2 7h5zm1 3V8H2l-.75 1L2 10h6zm0-5h6l.75-1L14 3H8v2z"/>
                                                </svg>
                                            </button>
                                            <a href="?delete=<?= $trip['id'] ?>" class="btn btn-icon btn-sm btn-outline-danger btn-delete" title="<?= __('common.delete') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <script>
            (function() {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.trip-select');
                const toolbar = document.getElementById('bulkToolbar');
                const countSpan = document.getElementById('selectedCount');
                
                function updateToolbar() {
                    const checked = document.querySelectorAll('.trip-select:checked').length;
                    countSpan.textContent = checked;
                    
                    if (checked > 0) {
                        toolbar.classList.remove('hidden');
                    } else {
                        toolbar.classList.add('hidden');
                    }
                    
                    selectAll.checked = checked === checkboxes.length && checkboxes.length > 0;
                    selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
                }
                
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateToolbar();
                });
                
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', updateToolbar);
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para seleccionar título de POI -->
<div class="modal fade" id="poiTitleModal" tabindex="-1" aria-labelledby="poiTitleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poiTitleModalLabel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-signpost-split me-2" viewBox="0 0 16 16">
                        <path d="M7 7V1.414a1 1 0 0 1 2 0V2h5a1 1 0 0 1 .8.4l.975 1.3a.5.5 0 0 1 0 .6L14.8 5.6a1 1 0 0 1-.8.4H9v10H7v-5H2a1 1 0 0 1-.8-.4L.225 9.3a.5.5 0 0 1 0-.6L1.2 7.4A1 1 0 0 1 2 7h5zm1 3V8H2l-.75 1L2 10h6zm0-5h6l.75-1L14 3H8v2z"/>
                    </svg>
                    <?= __('map.select_poi_title') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small"><?= __('map.select_poi_title_desc') ?></p>
                <div id="poiTitlesList" class="list-group">
                    <!-- Contenido dinámico -->
                </div>
                <div id="poiTitlesLoading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?= __('common.loading') ?></span>
                    </div>
                    <p class="mt-2 text-muted"><?= __('common.loading') ?></p>
                </div>
                <div id="poiTitlesEmpty" class="text-center py-4" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-inbox text-muted mb-2" viewBox="0 0 16 16">
                        <path d="M4.98 4a.5.5 0 0 0-.39.188L1.54 8H6a.5.5 0 0 1 .5.5 1.5 1.5 0 1 0 3 0A.5.5 0 0 1 10 8h4.46l-3.05-3.812A.5.5 0 0 0 11.02 4zm9.954 5H10.45a2.5 2.5 0 0 1-4.9 0H1.066l.32 2.562a.5.5 0 0 0 .497.438h12.234a.5.5 0 0 0 .496-.438zM3.809 3.563A1.5 1.5 0 0 1 4.981 3h6.038a1.5 1.5 0 0 1 1.172.563l3.7 4.625a.5.5 0 0 1 .105.374l-.39 3.124A1.5 1.5 0 0 1 14.117 13H1.883a1.5 1.5 0 0 1-1.489-1.314l-.39-3.124a.5.5 0 0 1 .106-.373z"/>
                    </svg>
                    <p class="text-muted"><?= __('map.no_poi_groups') ?></p>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="roundTripCheckbox">
                    <label class="form-check-label" for="roundTripCheckbox">
                        <?= __('routes.is_round_trip') ?> (<?= __('map.return_to_start') ?>)
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de carga/generación de ruta -->
<div class="modal fade" id="routeGeneratingModal" tabindex="-1" aria-labelledby="routeGeneratingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden"><?= __('common.loading') ?></span>
                </div>
                <h6><?= __('map.generating_route') ?></h6>
                <p class="text-muted small mb-2"><?= __('map.generating_route_desc') ?></p>
                <div class="progress mt-3" style="height: 20px;">
                    <div id="routeProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <p id="routeProgressText" class="text-muted small mt-2 mb-0">Iniciando...</p>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

function showCreateRouteFromPOIModal(tripId) {
    const modal = new bootstrap.Modal(document.getElementById('poiTitleModal'));
    const listContainer = document.getElementById('poiTitlesList');
    const loadingContainer = document.getElementById('poiTitlesLoading');
    const emptyContainer = document.getElementById('poiTitlesEmpty');

    listContainer.style.display = 'none';
    emptyContainer.style.display = 'none';
    loadingContainer.style.display = 'block';
    modal.show();

    fetch(BASE_URL + '/api/get_poi_titles.php?trip_id=' + tripId)
        .then(response => response.json())
        .then(data => {
            loadingContainer.style.display = 'none';

            if (data.success && data.data && data.data.length > 0) {
                listContainer.innerHTML = '';
                data.data.forEach(function (item) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    btn.innerHTML = '<span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt me-2" viewBox="0 0 16 16"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/><path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/></svg>' + item.title + '</span><span class="badge bg-primary rounded-pill">' + item.point_count + ' <?= __('map.points') ?></span>';
                    btn.onclick = function () {
                        modal.hide();
                        createRouteFromPOI(tripId, item.title);
                    };
                    listContainer.appendChild(btn);
                });
                listContainer.style.display = 'block';
            } else {
                emptyContainer.style.display = 'block';
            }
        })
        .catch(error => {
            loadingContainer.style.display = 'none';
            emptyContainer.style.display = 'block';
            console.error('Error al cargar títulos de POI:', error);
        });
}

function createRouteFromPOI(tripId, title) {
    const generatingModal = new bootstrap.Modal(document.getElementById('routeGeneratingModal'));
    const isRoundTrip = document.getElementById('roundTripCheckbox').checked;
    const progressBar = document.getElementById('routeProgressBar');
    const progressText = document.getElementById('routeProgressText');
    
    // Reset progress
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    progressText.textContent = 'Iniciando...';
    
    generatingModal.show();

    console.log('[POI Route] Iniciando generación de ruta');
    console.log('[POI Route] Trip ID:', tripId);
    console.log('[POI Route] Título:', title);
    console.log('[POI Route] Ida y vuelta:', isRoundTrip);

    // Primero hacer un request para obtener el total de batches
    fetch(BASE_URL + '/api/generate_route_from_poi.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ trip_id: tripId, title: title, is_round_trip: isRoundTrip })
    })
    .then(response => response.json())
    .then(data => {
        console.log('[POI Route] Respuesta:', JSON.stringify(data, null, 2));
        generatingModal.hide();

        if (data.success) {
            const pointsInfo = data.points_used < data.points_original
                ? ' (' + data.points_used + '/' + data.points_original + ' <?= __('map.points') ?>)'
                : '';
            alert('<?= __('map.route_generated') ?>' + pointsInfo);
            window.location.href = 'trip_edit_map.php?id=' + tripId;
        } else {
            alert(data.error || '<?= __('map.error_generating_route') ?>');
        }
    })
    .catch(error => {
        console.error('[POI Route] Error:', error);
        generatingModal.hide();
        alert('<?= __('map.error_generating_route') ?>');
    });
    
    // Simular progreso basado en tiempo estimado (2s por batch)
    const estimatedBatches = 3; // Estimación conservadora
    let currentBatch = 0;
    const progressInterval = setInterval(function() {
        if (currentBatch < estimatedBatches) {
            currentBatch++;
            const percent = Math.round((currentBatch / estimatedBatches) * 90); // Max 90% hasta completar
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            progressText.textContent = 'Procesando lote ' + currentBatch + ' de ~' + estimatedBatches + '...';
        } else {
            clearInterval(progressInterval);
        }
    }, 2500); // Actualizar cada 2.5s
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
