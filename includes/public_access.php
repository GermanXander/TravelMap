<?php
/**
 * Public Access Control Helper
 * 
 * Gestiona el control de acceso al sitio público con contraseñas compartidas
 * Permite acceso directo si el usuario admin está logueado
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../src/models/PasswordShare.php';
require_once __DIR__ . '/../src/models/Settings.php';

/**
 * Verifica si el usuario tiene acceso al sitio público
 * Si es admin logueado, permite acceso directo
 * Si el sitio requiere contraseña, verifica sesión de acceso público
 * 
 * @return array|null Array con 'access' => bool, 'trips' => string (viajes permitidos o '*')
 */
function check_public_access() {
    // Si el usuario es admin logueado, permitir acceso directo
    if (is_logged_in()) {
        return [
            'access' => true,
            'trips' => '*', // Admin ve todos los viajes
            'is_admin' => true
        ];
    }

    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Obtener configuración
    $settingsModel = new Settings(getDB());
    $requiresPass = $settingsModel->get('requires_pass', false);

    // Si no requiere contraseña, permitir acceso
    if (!$requiresPass) {
        return [
            'access' => true,
            'trips' => '*',
            'is_admin' => false
        ];
    }

    // Verificar contraseña en sesión
    $allowedTrips = '*';
    $hasValidPassword = false;

    if (isset($_POST['password'])) {
        // Intentar validar contraseña ingresada
        $passwordShareModel = new PasswordShare(getDB());
        $validationResult = $passwordShareModel->validatePassword($_POST['password']);
        
        if ($validationResult !== false) {
            $_SESSION['public_password_id'] = $validationResult['id'];
            $_SESSION['public_password_trips'] = $validationResult['trips'];
            $hasValidPassword = true;
            $allowedTrips = $validationResult['trips'];
        } else {
            return [
                'access' => false,
                'trips' => null,
                'is_admin' => false,
                'error' => __('public.requires_pass_invalid') ?? 'Contraseña inválida'
            ];
        }
    } elseif (isset($_SESSION['public_password_id'])) {
        // Revalidar contraseña en sesión
        $passwordShareModel = new PasswordShare(getDB());
        $allowedTrips = $passwordShareModel->validatePasswordById($_SESSION['public_password_id']);
        
        if ($allowedTrips !== false) {
            $hasValidPassword = true;
            $_SESSION['public_password_trips'] = $allowedTrips;
        } else {
            // Contraseña expirada o desactivada
            unset($_SESSION['public_password_id']);
            unset($_SESSION['public_password_trips']);
            return [
                'access' => false,
                'trips' => null,
                'is_admin' => false,
                'error' => __('public.requires_pass_expired') ?? 'La contraseña ha expirado'
            ];
        }
    } elseif (isset($_SESSION['public_password_trips'])) {
        // Fallback para sesiones antiguas: invalidar
        unset($_SESSION['public_password_trips']);
    }

    if ($hasValidPassword) {
        return [
            'access' => true,
            'trips' => $allowedTrips,
            'is_admin' => false
        ];
    }

    // No tiene acceso válido
    return [
        'access' => false,
        'trips' => null,
        'is_admin' => false,
        'error' => null
    ];
}

/**
 * Verifica si un viaje específico es accesible
 * 
 * @param int $tripId ID del viaje a verificar
 * @param array $accessInfo Array retornado por check_public_access()
 * @return bool True si el viaje es accesible
 */
function is_trip_accessible($tripId, $accessInfo) {
    if (!$accessInfo['access']) {
        return false;
    }

    if ($accessInfo['trips'] === '*') {
        return true; // Acceso a todos los viajes
    }

    // Verificar si el viaje está en la lista de permitidos
    $allowedTrips = explode(',', $accessInfo['trips']);
    return in_array((string)$tripId, array_map('trim', $allowedTrips));
}

/**
 * Muestra la página de login para acceder al sitio público
 * 
 * @param string $siteTitle Título del sitio
 * @param string $version Versión de la app
 * @param string|null $passwordError Mensaje de error si la contraseña fue inválida
 */
function show_public_login_page($siteTitle, $version, $passwordError = null) {
    $siteFavicon = SITE_FAVICON ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="<?= current_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($siteTitle) ?> - <?= __('public.requires_pass_title') ?? 'Acceso Requerido' ?></title>
        <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/public_map.css?v=<?php echo $version; ?>">
        <?php if (!empty($siteFavicon)): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($siteFavicon) ?>">
        <?php endif; ?>
        <style>
            .login-page {
                background: var(--primary-dark-slate, #1e293b);
                background-image: 
                    radial-gradient(circle at 25% 25%, rgba(51, 65, 85, 0.6) 0%, transparent 50%),
                    radial-gradient(circle at 75% 75%, rgba(51, 65, 85, 0.4) 0%, transparent 50%);
            }
            :root {
                --primary-dark-slate: #1e293b;
                --hover-dark-slate: #334155;
            }
        </style>
    </head>
    <body class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-map" viewBox="0 0 16 16" style="width: 48px; height: 48px; color: #1e293b; margin: 0 auto 10px; display: block;">
                        <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
                    </svg>
                    <h1 class="login-title">TravelMap</h1>
                    <!-- <h1 class="login-title"><?= htmlspecialchars($siteTitle) ?></h1> -->
                    <p class="login-subtitle"><?= __('public.requires_pass_description') ?? 'Ingrese la contraseña para acceder al mapa de viajes' ?></p>
                </div>
                
                <form method="post" class="login-form">
                    <div class="form-group mb-3">
                        <label for="password" class="form-label"><?= __('public.password') ?? 'Contraseña' ?></label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               required 
                               autofocus
                               placeholder="<?= __('public.enter_password') ?? 'Ingrese la contraseña' ?>">
                    </div>
                    
                    <?php if (!empty($passwordError)): ?>
                        <div class="alert alert-danger mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; display: inline-block; vertical-align: middle;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span><?= htmlspecialchars($passwordError) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px; font-weight: 600;">
                        <?= __('public.access_map') ?? 'Acceder al Mapa' ?>
                    </button>
                </form>
                
                <div class="login-footer">
                    <a href="<?= BASE_URL ?>/admin/" class="text-muted"><?= __('app.admin_panel') ?? 'Panel de Administración' ?></a>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS Bundle - Local -->
        <script src="<?= ASSETS_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
