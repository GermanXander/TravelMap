<?php
/**
 * Gestión de Usuarios
 * 
 * Listado y gestión de usuarios del sistema
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/PasswordShare.php';

$userModel = new User();
$users = $userModel->getAll('username ASC');
$totalUsers = $userModel->count();

// Manejar eliminación de usuario
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = (int) $_GET['id'];
    $currentUserId = $_SESSION['user_id'] ?? null;
    
    // Validar que no se pueda eliminar el último usuario
    if ($totalUsers <= 1) {
        $_SESSION['error_message'] = __('users.cannot_delete_last');
        header('Location: users.php');
        exit;
    }
    
    // Validar que no se elimine a sí mismo
    if ($userId === $currentUserId) {
        $_SESSION['error_message'] = __('users.cannot_delete_self');
        header('Location: users.php');
        exit;
    }
    
    // Eliminar usuario
    if ($userModel->delete($userId)) {
        $_SESSION['success_message'] = __('users.deleted_success');
    } else {
        $_SESSION['error_message'] = __('users.error_deleting');
    }
    
    header('Location: users.php');
    exit;
}

// Manejar toggle de contraseña
if (isset($_GET['action']) && $_GET['action'] === 'toggle_password' && isset($_GET['id'])) {
    $shareId = (int) $_GET['id'];
    $passwordShareModel = new PasswordShare($conn);
    
    if ($passwordShareModel->toggleActive($shareId)) {
        $_SESSION['success_message'] = __('users.password_updated');
    } else {
        $_SESSION['error_message'] = __('users.error_saving');
    }
    
    header('Location: users.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <?= __('users.management') ?>
        </h1>
        <p class="page-subtitle"><?= __('users.manage_users') ?></p>
    </div>
    <div class="page-actions">
        <a href="user_form.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            <?= __('users.new_user') ?>
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (count($users) > 0): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th><?= __('users.username') ?></th>
                            <th style="width: 180px;"><?= __('users.created_at') ?></th>
                            <th style="width: 100px;" class="table-actions"><?= __('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="text-muted"><?= $user['id'] ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="sidebar-user-avatar" style="width: 32px; height: 32px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="cell-title"><?= htmlspecialchars($user['username']) ?></span>
                                            <?php if ($user['id'] == ($_SESSION['user_id'] ?? null)): ?>
                                                <span class="badge badge-info" style="margin-left: 8px;"><?= __('common.you') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="cell-date">
                                        <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <a href="user_form.php?id=<?= $user['id'] ?>" 
                                           class="btn btn-icon btn-sm btn-outline-primary" 
                                           title="<?= __('users.edit_user') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                        
                                        <?php if ($totalUsers > 1 && $user['id'] != ($_SESSION['user_id'] ?? null)): ?>
                                            <button type="button" 
                                                    class="btn btn-icon btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                                    title="<?= __('users.delete_user') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-icon btn-sm btn-secondary" 
                                                    disabled
                                                    style="opacity: 0.4;"
                                                    title="<?= $totalUsers <= 1 ? __('users.cannot_delete_last') : __('users.cannot_delete_self') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-card-footer" style="font-size: 12px; color: var(--admin-text-muted);">
                <?= $totalUsers ?> <?= __('users.username') ?>(s)
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h4 class="empty-state-title"><?= __('users.no_users') ?? 'No users found' ?></h4>
                <p class="empty-state-text"><?= __('users.create_first') ?? 'Create your first user to get started' ?></p>
                <a href="user_form.php" class="btn btn-primary"><?= __('users.new_user') ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Password Shares Section -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <circle cx="12" cy="16" r="1"></circle>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <?= __('users.share_with_password') ?>
        </h1>
        <p class="page-subtitle"><?= __('users.manage_users') ?></p>
    </div>
    <div class="page-actions">
        <a href="share_form.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-key me-2" viewBox="0 0 16 16">
                <path d="M0 8a4 4 0 0 1 7.465-2H14a.5.5 0 0 1 .354.146l1.5 1.5a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0L13 9.207l-.646.647a.5.5 0 0 1-.708 0L11 9.207l-.646.647a.5.5 0 0 1-.708 0L9 9.207l-.646.647A.5.5 0 0 1 8 10h-.535A4 4 0 0 1 0 8m4-3a3 3 0 1 0 2.712 4.285A.5.5 0 0 1 7.163 9h.63l.853-.854a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.793-.793-1-1h-6.63a.5.5 0 0 1-.451-.285A3 3 0 0 0 4 5z"/>
                <path d="M4 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
            </svg>
            <?= __('users.new_password_share') ?>
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../src/models/PasswordShare.php';
$passwordShareModel = new PasswordShare($conn);
$passwordShares = $passwordShareModel->getActive();
?>

<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (count($passwordShares) > 0): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?= __('users.password_share') ?></th>
                            <th><?= __('share.trips') ?? 'Trips' ?></th>
                            <th style="width: 300px;"><?= __('share.description_field') ?? 'Description' ?></th>
                            <th style="width: 150px;"><?= __('users.valid_until') ?></th>
                            <th style="width: 100px;" class="table-actions"><?= __('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passwordShares as $share): ?>
                            <tr>
                                <td>
                                    <code class="clickable-copy" 
                                          data-password="<?= htmlspecialchars($share['password'], ENT_QUOTES) ?>" 
                                          title="<?= __('users.copy_password') ?? 'Click to copy password' ?>"
                                          style="font-family: monospace; background: var(--admin-bg-secondary); padding: 2px 6px; border-radius: 3px;">
                                        <?= htmlspecialchars(substr($share['password'], 0, 20) . (strlen($share['password']) > 20 ? '...' : '')) ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="cell-text">
                                        <?php if ($share['trips'] === '*'): ?>
                                            <?= htmlspecialchars(__('share.all_future_label') ?? 'Todos Incluso Futuros') ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($passwordShareModel->getTripNames($share['trips'])) ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-text">
                                        <?= htmlspecialchars($share['description'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-date">
                                        <?= $share['expires_at'] === null ? htmlspecialchars(__('share.never_expires') ?? 'Never') : date('d/m/Y', strtotime($share['expires_at'])) ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <button type="button" 
                                                class="btn btn-icon btn-sm btn-outline-secondary" 
                                                onclick="togglePasswordActive(<?= $share['id'] ?>, <?= $share['active'] ? 'true' : 'false' ?>)"
                                                title="<?= __('users.toggle_active') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <?php if ($share['active']): ?>
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                <?php else: ?>
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                <?php endif; ?>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="copyMessage" class="copy-message"></div>

            <div class="admin-card-footer" style="font-size: 12px; color: var(--admin-text-muted);">
                <?= count($passwordShares) ?> <?= __('users.password_share') ?>(s)
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <circle cx="12" cy="16" r="1"></circle>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <h4 class="empty-state-title"><?= __('users.no_passwords') ?? 'No active passwords found' ?></h4>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    if (confirm(`<?= __('users.confirm_delete') ?? 'Are you sure you want to delete this user?' ?>\n\n${username}`)) {
        window.location.href = `users.php?action=delete&id=${userId}`;
    }
}

function copyPasswordToClipboard(password) {
    if (!password) {
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(password)
            .then(() => showCopyMessage('<?= __('users.copied_to_clipboard') ?? 'Copied to clipboard' ?>'))
            .catch(() => prompt('<?= __('users.copy_password') ?? 'Copy password' ?>', password));
    } else {
        prompt('<?= __('users.copy_password') ?? 'Copy password' ?>', password);
    }
}

function showCopyMessage(message) {
    const messageEl = document.getElementById('copyMessage');
    if (!messageEl) {
        return;
    }

    messageEl.textContent = message;
    messageEl.classList.add('visible');

    setTimeout(() => {
        messageEl.classList.remove('visible');
        messageEl.textContent = '';
    }, 2200);
}

function togglePasswordActive(shareId, currentlyActive) {
    const action = currentlyActive ? '<?= __('users.password_deactivated') ?>' : '<?= __('users.password_activated') ?>';
    if (confirm(`<?= __('users.confirm_toggle_password') ?? 'Are you sure?' ?>\n\n${action}`)) {
        window.location.href = `users.php?action=toggle_password&id=${shareId}`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.clickable-copy').forEach(element => {
        element.addEventListener('click', function() {
            copyPasswordToClipboard(this.dataset.password);
        });
    });
});
</script>

<style>
.clickable-copy {
    cursor: pointer;
}
.clickable-copy:hover {
    text-decoration: underline;
}
.copy-message {
    display: inline-block;
    margin: 12px 0 0;
    color: var(--admin-success);
    opacity: 0;
    transition: opacity 0.2s ease;
}
.copy-message.visible {
    opacity: 1;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
