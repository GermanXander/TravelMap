<?php
/**
 * Formulario de Compartir con Contraseña
 *
 * Crear una nueva contraseña para compartir viajes
 */

// Cargar configuración y dependencias ANTES de header.php para permitir redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/PasswordShare.php';
require_once __DIR__ . '/../src/models/Trip.php';

$passwordShareModel = new PasswordShare($conn);
$tripModel = new Trip();
$errors = [];
$success = false;

// Obtener todos los viajes publicados
$allTrips = $tripModel->getAll('title ASC', 'published');

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'password' => trim($_POST['password'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'validity_period' => $_POST['validity_period'] ?? '1_week',
        'selected_trips' => $_POST['selected_trips'] ?? []
    ];

    // Generar contraseña si está vacía
    if (empty($data['password'])) {
        $data['password'] = $passwordShareModel->generateUniquePassword();
    }

    // Validar contraseña única
    if ($passwordShareModel->passwordExists($data['password'])) {
        $errors['password'] = __('share.password_exists') ?? 'Password already exists';
    }

    if (mb_strlen($data['description']) > 100) {
        $errors['description'] = __('share.description_length') ?? 'Description must be 100 characters or fewer';
    }

    // Calcular fecha de expiración
    $expiresAt = null;
    switch ($data['validity_period']) {
        case '1_week':
            $expiresAt = new DateTime();
            $expiresAt->modify('+1 week');
            break;
        case '1_month':
            $expiresAt = new DateTime();
            $expiresAt->modify('+1 month');
            break;
        case '1_year':
            $expiresAt = new DateTime();
            $expiresAt->modify('+1 year');
            break;
        case 'forever':
            $expiresAt = null;
            break;
        default:
            $expiresAt = new DateTime();
            $expiresAt->modify('+1 week');
    }

    // Procesar viajes seleccionados
    $trips = '';
    if (in_array('all', $data['selected_trips'])) {
        $trips = '*';
    } elseif (in_array('all_future', $data['selected_trips'])) {
        $trips = '*';
    } else {
        $selectedIds = array_filter($data['selected_trips'], 'is_numeric');
        if (!empty($selectedIds)) {
            $trips = implode(',', $selectedIds);
        } else {
            $errors['trips'] = __('share.select_trips') ?? 'Select at least one trip';
        }
    }

    if (empty($errors)) {
        $expiresDate = $expiresAt ? $expiresAt->format('Y-m-d') : null;
        if ($passwordShareModel->create($data['password'], $trips, $expiresDate, $data['description'])) {
            $_SESSION['success_message'] = __('users.password_created');
            header("Location: users.php");
            exit;
        } else {
            $errors['general'] = __('users.error_creating_password');
        }
    }
}

// Ahora sí incluir header.php (después de procesar y posibles redirects)
require_once __DIR__ . '/../includes/header.php';

// Valores por defecto para formulario
$form_data = [
    'password' => $passwordShareModel->generateUniquePassword(),
    'description' => $_POST['description'] ?? '',
    'validity_period' => $_POST['validity_period'] ?? '1_week',
    'selected_trips' => $_POST['selected_trips'] ?? []
];
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-key me-2" viewBox="0 0 16 16">
                <path d="M0 8a4 4 0 0 1 7.465-2H14a.5.5 0 0 1 .354.146l1.5 1.5a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0L13 9.207l-.646.647a.5.5 0 0 1-.708 0L11 9.207l-.646.647a.5.5 0 0 1-.708 0L9 9.207l-.646.647A.5.5 0 0 1 8 10h-.535A4 4 0 0 1 0 8m4-3a3 3 0 1 0 2.712 4.285A.5.5 0 0 1 7.163 9h.63l.853-.854a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.793-.793-1-1h-6.63a.5.5 0 0 1-.451-.285A3 3 0 0 0 4 5z"/>
                <path d="M4 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
            </svg>
            <?= __('users.new_password_share') ?>
        </h1>
        <p class="text-muted mb-4">
            <?= __('share.description') ?? 'Create a password to share specific trips with others' ?>
        </p>
    </div>
    <div class="col-md-4 text-end">
        <a href="users.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 0 1 .708.708L2.707 7.5H14.5a.5.5 0 0 1 .5.5z"/>
            </svg>
            <?= __('common.back') ?>
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($errors['general']) ?></span>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><?= __('share.password_details') ?? 'Password Details' ?></h3>
    </div>
    <form method="post" class="admin-card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="password" class="form-label">
                        <?= __('users.password_share') ?> <span class="required">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           value="<?= htmlspecialchars($form_data['password']) ?>"
                           maxlength="255" 
                           required
                           pattern="[a-zA-Z0-9]+"
                           title="<?= __('share.password_format') ?? 'Only letters and numbers allowed' ?>">
                    <div class="form-hint">
                        <?= __('share.password_hint') ?? 'Leave empty to generate a random password' ?>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="description" class="form-label">
                        <?= __('share.description_field') ?? 'Description' ?>
                    </label>
                    <input type="text"
                           class="form-control"
                           id="description"
                           name="description"
                           value="<?= htmlspecialchars($form_data['description']) ?>"
                           maxlength="100">
                    <div class="form-hint">
                        <?= __('share.description_hint') ?? 'Optional note for this password' ?>
                    </div>
                    <?php if (!empty($errors['description'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="validity_period" class="form-label">
                        <?= __('share.validity_period') ?? 'Valid for' ?> <span class="required">*</span>
                    </label>
                    <select class="form-control" id="validity_period" name="validity_period" required>
                        <option value="1_week" <?= $form_data['validity_period'] === '1_week' ? 'selected' : '' ?>>
                            <?= __('share.1_week') ?? '1 Week' ?>
                        </option>
                        <option value="1_month" <?= $form_data['validity_period'] === '1_month' ? 'selected' : '' ?>>
                            <?= __('share.1_month') ?? '1 Month' ?>
                        </option>
                        <option value="1_year" <?= $form_data['validity_period'] === '1_year' ? 'selected' : '' ?>>
                            <?= __('share.1_year') ?? '1 Year' ?>
                        </option>
                        <option value="forever" <?= $form_data['validity_period'] === 'forever' ? 'selected' : '' ?>>
                            <?= __('share.forever') ?? 'Forever' ?>
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <hr>

        <div class="form-group">
            <label class="form-label">
                <?= __('share.select_trips') ?? 'Select trips to share' ?> <span class="required">*</span>
            </label>
            <div class="trip-selector">
                <div class="trip-filters mb-3">
                    <button type="button" class="filter-btn" id="selectAll">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"></polyline>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                        <?= __('share.all_trips') ?? 'All Trips' ?>
                    </button>
                    <button type="button" class="filter-btn" id="selectNone">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"></polyline>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                            <line x1="3" y1="3" x2="21" y2="21"></line>
                        </svg>
                        <?= __('share.no_trips') ?? 'No Trips' ?>
                    </button>
                    <button type="button" class="filter-btn" id="selectAllFuture">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <?= __('share.all_future') ?? 'All + Future' ?>
                    </button>
                </div>
                
                <div class="trip-list">
                    <?php if (empty($allTrips)): ?>
                        <div class="text-center text-muted py-4">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="mb-3">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                            <p class="mb-2">No trips found</p>
                            <small class="text-muted">Create trips first, then publish them to share with passwords</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allTrips as $trip): ?>
                            <label class="trip-item">
                                <input type="checkbox" 
                                       name="selected_trips[]" 
                                       value="<?= $trip['id'] ?>" 
                                       class="trip-checkbox"
                                       <?= in_array($trip['id'], $form_data['selected_trips']) ? 'checked' : '' ?>>
                                <span class="trip-name">
                                    <strong><?= htmlspecialchars($trip['title']) ?></strong>
                                    <small class="text-muted">
                                        <?= date('M Y', strtotime($trip['start_date'])) ?>
                                        <?php if ($trip['end_date'] && $trip['end_date'] !== $trip['start_date']): ?>
                                            - <?= date('M Y', strtotime($trip['end_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($errors['trips'])): ?>
                <div class="form-error"><?= htmlspecialchars($errors['trips']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check me-1" viewBox="0 0 16 16">
                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 0 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                </svg>
                <?= __('common.save') ?>
            </button>
            <a href="users.php" class="btn btn-outline-secondary">
                <?= __('common.cancel') ?>
            </a>
        </div>
    </form>
</div>

<style>
.trip-selector {
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 16px;
    background: var(--admin-bg);
}

.trip-filters {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.filter-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    background: var(--admin-bg);
    color: var(--admin-text);
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-btn:hover,
.filter-btn.active {
    background: var(--admin-primary);
    border-color: var(--admin-primary);
    color: white;
}

.trip-list {
    max-height: 300px;
    overflow-y: auto;
}

.trip-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}

.trip-item:hover {
    background: var(--admin-bg-hover);
}

.trip-checkbox {
    margin: 0;
}

.trip-name {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.trip-name strong {
    font-weight: 500;
}

.trip-name small {
    color: var(--admin-text-muted);
    font-size: 12px;
}
</style>

<script>
function clearHiddenInputs() {
    document.querySelectorAll('input[name="selected_trips[]"][type="hidden"]').forEach(el => el.remove());
}

function clearButtonHighlights() {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
}

function updateButtonStates() {
    clearButtonHighlights();
    
    const hiddenInputs = document.querySelectorAll('input[name="selected_trips[]"][type="hidden"]');
    const checkedBoxes = document.querySelectorAll('.trip-checkbox:checked');
    
    if (hiddenInputs.length > 0) {
        const value = hiddenInputs[0].value;
        if (value === 'all') {
            document.getElementById('selectAll').classList.add('active');
        } else if (value === 'all_future') {
            document.getElementById('selectAllFuture').classList.add('active');
        }
    } else if (checkedBoxes.length === document.querySelectorAll('.trip-checkbox').length && checkedBoxes.length > 0) {
        document.getElementById('selectAll').classList.add('active');
    }
}

document.getElementById('selectAll').addEventListener('click', function() {
    clearHiddenInputs();
    clearButtonHighlights();
    
    // Check all visible checkboxes
    document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = true);
    
    // Add hidden input for 'all' to indicate all trips selected
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'selected_trips[]';
    hiddenInput.value = 'all';
    document.querySelector('form').appendChild(hiddenInput);
    
    this.classList.add('active');
});

document.getElementById('selectNone').addEventListener('click', function() {
    clearHiddenInputs();
    clearButtonHighlights();
    
    document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = false);
    
    this.classList.add('active');
    setTimeout(() => this.classList.remove('active'), 200); // Remove highlight after brief moment
});

document.getElementById('selectAllFuture').addEventListener('click', function() {
    clearHiddenInputs();
    clearButtonHighlights();
    
    // Check all visible checkboxes
    document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = true);
    
    // Add hidden input for 'all_future'
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'selected_trips[]';
    hiddenInput.value = 'all_future';
    document.querySelector('form').appendChild(hiddenInput);
    
    this.classList.add('active');
});

// Handle individual checkbox changes
document.querySelectorAll('.trip-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        clearHiddenInputs();
        updateButtonStates();
    });
});

// Initialize button states on page load
updateButtonStates();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>