<?php
/**
 * TravelMap – Instalador / Actualizador
 *
 * Acceso: http://[host]/TravelMap/install/
 *
 * ┌─ Instalación nueva ──────────────────────────────────────────────────┐
 * │  Paso 1 – Verificación de requisitos                                  │
 * │  Paso 2 – Configuración (db.php + carpeta BASE_URL)                   │
 * │  Paso 3 – Inicializar base de datos (ejecuta database.sql)            │
 * │  Paso 4 – Crear usuario administrador                                 │
 * │  Paso 5 – Listo                                                       │
 * └───────────────────────────────────────────────────────────────────────┘
 * ┌─ Actualización ──────────────────────────────────────────────────────┐
 * │  Muestra estado de migraciones + validación de esquema               │
 * │  Botón para ejecutar migraciones pendientes                           │
 * │  Herramienta para crear usuario adicional                             │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * SEGURIDAD: Eliminar o proteger esta carpeta después de usar.
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// ── Sesión y CSRF ─────────────────────────────────────────────────────────────
session_start();

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

// ── Autenticación del instalador ──────────────────────────────────────────────
// Solo exigir login si db.php existe Y ya hay al menos un usuario creado.
// Durante la instalación inicial (sin tablas o sin usuarios) se permite el acceso libre.
(function () {
    $dbPhpPath = ROOT . '/config/db.php';
    if (!file_exists($dbPhpPath)) {
        return; // Instalación nueva sin config: acceso libre
    }

    // Verificar si ya existe al menos un usuario en la BD.
    // Usamos PDO directo (leyendo constantes via Reflection) para evitar el die()
    // que tiene el constructor de Database en caso de fallo de conexión.
    $hasUsers = false;
    try {
        if (!class_exists('Database', false)) {
            // Incluir sin ejecutar getDB() — solo define la clase
            require_once $dbPhpPath;
        }
        $ref  = new ReflectionClass('Database');
        $host    = $ref->getConstant('DB_HOST');
        $dbname  = $ref->getConstant('DB_NAME');
        $user    = $ref->getConstant('DB_USER');
        $pass    = $ref->getConstant('DB_PASS');
        $charset = $ref->getConstant('DB_CHARSET');

        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset={$charset}",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt && $stmt->fetchColumn()) {
            $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        }
    } catch (Throwable $e) {
        return; // Error de conexión o tablas inexistentes: dejar pasar para mostrar el error en la UI
    }

    if (!$hasUsers) {
        return; // Sin usuarios: instalación incompleta, acceso libre
    }

    // Manejar logout del instalador
    if (isset($_GET['installer_logout'])) {
        unset($_SESSION['installer_auth'], $_SESSION['installer_user']);
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    }

    // Ya autenticado
    if (!empty($_SESSION['installer_auth'])) {
        return;
    }

    // Procesar formulario de login del instalador
    $loginError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'installer_login') {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['_csrf'], $token)) {
            $loginError = 'Error de seguridad. Recargá la página e intentá nuevamente.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            try {
                $db   = getDB();
                $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['installer_auth'] = true;
                    $_SESSION['installer_user'] = $user['username'];
                    header('Location: index.php');
                    exit;
                } else {
                    // Retardo constante para evitar timing attacks
                    password_verify('dummy', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234');
                    $loginError = 'Usuario o contraseña incorrectos.';
                }
            } catch (Throwable $e) {
                $loginError = 'Error de conexión. Verificá config/db.php.';
            }
        }
    }

    // Mostrar formulario de acceso al instalador y detener ejecución
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMap – Acceso al Instalador</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .login-wrap {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,.4);
            padding: 40px;
            width: 100%;
            max-width: 380px;
        }
        .login-wrap h1 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .login-wrap p.sub {
            font-size: .88rem;
            color: #64748b;
            margin-bottom: 28px;
        }
        label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: .95rem;
            margin-bottom: 16px;
            transition: border-color .2s;
        }
        input:focus { outline: none; border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,.15); }
        .btn {
            width: 100%;
            padding: 10px;
            background: #1e293b;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .btn:hover { background: #334155; }
        .alert-error {
            background: #fce8e6;
            border-left: 4px solid #ea4335;
            color: #7d191c;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: .88rem;
            margin-bottom: 18px;
        }
        .lock-icon { text-align: center; font-size: 2.5rem; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="lock-icon">🔒</div>
        <h1>Instalador / Actualizador</h1>
        <p class="sub">Ingresá con tu cuenta de administrador para continuar.</p>
        <?php if ($loginError): ?>
        <div class="alert-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="index.php">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf'], ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="installer_login">
            <label for="il_user">Usuario</label>
            <input type="text" id="il_user" name="username" required autofocus autocomplete="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <label for="il_pass">Contraseña</label>
            <input type="password" id="il_pass" name="password" required autocomplete="current-password">
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
})();

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($_SESSION['_csrf'], ENT_QUOTES) . '">';
}

function csrfCheck(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'], $token)) {
        die('<p style="color:red;font-family:monospace">Error de seguridad CSRF. Recarga la página e intenta nuevamente.</p>');
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Flash messages ────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

// ── Estado del entorno ────────────────────────────────────────────────────────
$configPhpExists = file_exists(ROOT . '/config/config.php');
$dbPhpExists     = file_exists(ROOT . '/config/db.php');
$dbConnection    = null;
$dbError         = null;

if ($dbPhpExists) {
    try {
        require_once ROOT . '/config/db.php';
        $dbConnection = getDB();
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Ejecuta el contenido de database.sql sobre la conexión dada.
 * Divide en sentencias individuales ignorando comentarios y líneas vacías.
 */
function executeSqlFile(PDO $db, string $filePath): void
{
    $sql = file_get_contents($filePath);
    // Quitar comentarios de una línea (--) pero respetar strings
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    // Dividir por ; y ejecutar cada sentencia
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $db->exec($stmt);
        }
    }
}

/**
 * Escribe db.php con las credenciales dadas.
 * Solo reemplaza las constantes; el resto del archivo queda intacto.
 */
function writeDbConfig(string $host, string $name, string $user, string $pass, string $charset): bool
{
    $example = ROOT . '/config/db.example.php';
    if (!file_exists($example)) {
        return false;
    }
    $content = file_get_contents($example);

    $map = [
        "/private const DB_HOST\s*=\s*'[^']*';/"    => "private const DB_HOST    = '" . addslashes($host)    . "';",
        "/private const DB_NAME\s*=\s*'[^']*';/"    => "private const DB_NAME    = '" . addslashes($name)    . "';",
        "/private const DB_USER\s*=\s*'[^']*';/"    => "private const DB_USER    = '" . addslashes($user)    . "';",
        "/private const DB_PASS\s*=\s*'[^']*';/"    => "private const DB_PASS    = '" . addslashes($pass)    . "';",
        "/private const DB_CHARSET\s*=\s*'[^']*';/" => "private const DB_CHARSET = '" . addslashes($charset) . "';",
    ];
    foreach ($map as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    return file_put_contents(ROOT . '/config/db.php', $content) !== false;
}

/**
 * Actualiza (o crea) config.php con la carpeta BASE_URL indicada.
 */
function writeConfigFolder(string $folder): bool
{
    $folder  = ltrim($folder, '/');
    $folder  = $folder === '' ? '' : '/' . $folder;

    $target  = ROOT . '/config/config.php';
    $source  = ROOT . '/config/config.example.php';
    $content = file_exists($target) ? file_get_contents($target) : file_get_contents($source);

    // Reemplazar la línea $folder = '...';
    $updated = preg_replace(
        '/\$folder\s*=\s*\'[^\']*\';/',
        "\$folder   = '" . addslashes($folder) . "';",
        $content
    );

    return file_put_contents($target, $updated) !== false;
}

// ── Manejadores POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // ── 1. Guardar configuración de DB y carpeta ──────────────────────────────
    if ($action === 'save_config') {
        $host    = trim($_POST['db_host']    ?? 'localhost');
        $name    = trim($_POST['db_name']    ?? '');
        $user    = trim($_POST['db_user']    ?? '');
        $pass    =      $_POST['db_pass']    ?? '';
        $charset = trim($_POST['db_charset'] ?? 'utf8mb4');
        $folder  = trim($_POST['site_folder'] ?? '/TravelMap');

        if ($name === '' || $user === '') {
            setFlash('error', 'El nombre de la base de datos y el usuario son obligatorios.');
            header('Location: index.php');
            exit;
        }

        // Probar conexión antes de escribir
        try {
            $testPdo = new PDO(
                "mysql:host={$host};dbname={$name};charset={$charset}",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            unset($testPdo);
        } catch (PDOException $e) {
            setFlash('error', 'Conexión fallida: ' . $e->getMessage());
            header('Location: index.php');
            exit;
        }

        if (!writeDbConfig($host, $name, $user, $pass, $charset)) {
            setFlash('error', 'No se pudo escribir config/db.php. Verificar permisos de escritura.');
            header('Location: index.php');
            exit;
        }

        if (!writeConfigFolder($folder)) {
            setFlash('error', 'No se pudo escribir config/config.php. Verificar permisos de escritura.');
            header('Location: index.php');
            exit;
        }

        setFlash('success', 'Configuración guardada. Ahora inicializá la base de datos.');
        header('Location: index.php');
        exit;
    }

    // ── 2. Inicializar BD (ejecutar database.sql) ─────────────────────────────
    if ($action === 'init_db') {
        if (!$dbConnection) {
            setFlash('error', 'Sin conexión a la base de datos. Completar el paso anterior primero.');
            header('Location: index.php');
            exit;
        }

        $sqlFile = ROOT . '/database.sql';
        if (!file_exists($sqlFile)) {
            setFlash('error', 'No se encontró database.sql en la raíz del proyecto.');
            header('Location: index.php');
            exit;
        }

        try {
            executeSqlFile($dbConnection, $sqlFile);

            require_once __DIR__ . '/MigrationRunner.php';
            $runner = new MigrationRunner($dbConnection);
            $runner->markAllAsApplied();

            setFlash('success', 'Base de datos inicializada correctamente. Creá el usuario administrador.');
            header('Location: index.php?step=create_admin');
            exit;
        } catch (Throwable $e) {
            setFlash('error', 'Error al inicializar la BD: ' . $e->getMessage());
            header('Location: index.php');
            exit;
        }
    }

    // ── 3. Crear usuario administrador ────────────────────────────────────────
    if ($action === 'create_admin') {
        if (!$dbConnection) {
            setFlash('error', 'Sin conexión a la base de datos.');
            header('Location: index.php?step=create_admin');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password =      $_POST['password'] ?? '';
        $confirm  =      $_POST['password_confirm'] ?? '';

        $err = null;
        if (strlen($username) < 3 || strlen($username) > 50) {
            $err = 'El usuario debe tener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $err = 'El usuario solo puede contener letras, números y guiones bajos.';
        } elseif (strlen($password) < 8) {
            $err = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $confirm) {
            $err = 'Las contraseñas no coinciden.';
        }

        if ($err) {
            setFlash('error', $err);
            header('Location: index.php?step=create_admin');
            exit;
        }

        // Verificar usuario duplicado
        $stmt = $dbConnection->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            setFlash('error', "El usuario '{$username}' ya existe.");
            header('Location: index.php?step=create_admin');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $dbConnection->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);

        setFlash('success', "Usuario administrador «{$username}» creado correctamente.");
        header('Location: index.php?step=done');
        exit;
    }

    // ── 4. Ejecutar migraciones pendientes ────────────────────────────────────
    if ($action === 'run_migrations') {
        if (!$dbConnection) {
            setFlash('error', 'Sin conexión a la base de datos.');
            header('Location: index.php');
            exit;
        }

        require_once __DIR__ . '/MigrationRunner.php';
        $runner  = new MigrationRunner($dbConnection);
        $results = $runner->runPending();

        $_SESSION['migration_results'] = $results;

        $applied = count(array_filter($results, fn($r) => $r['success'] && !$r['skipped']));
        $failed  = count(array_filter($results, fn($r) => !$r['success']));

        if (empty($results)) {
            setFlash('info', 'No hay migraciones pendientes.');
        } elseif ($failed > 0) {
            setFlash('error', "{$failed} migración(es) fallaron. Ver detalles abajo.");
        } else {
            setFlash('success', "{$applied} migración(es) aplicadas correctamente.");
        }

        header('Location: index.php');
        exit;
    }

    header('Location: index.php');
    exit;
}

// ── Estado de la página (GET) ─────────────────────────────────────────────────
$step             = $_GET['step'] ?? '';
$migrationResults = $_SESSION['migration_results'] ?? null;
unset($_SESSION['migration_results']);

$migrations    = [];
$schemaIssues  = [];
$pendingCount  = 0;
$userCount     = 0;
$tablesExist   = false;
$runner        = null;

if ($dbConnection) {
    $stmt        = $dbConnection->query("SHOW TABLES LIKE 'users'");
    $tablesExist = (bool) $stmt->fetchColumn();

    if ($tablesExist) {
        $userCount = (int) $dbConnection->query('SELECT COUNT(*) FROM users')->fetchColumn();

        require_once __DIR__ . '/MigrationRunner.php';
        $runner       = new MigrationRunner($dbConnection);
        $migrations   = $runner->getStatus();
        $schemaIssues = $runner->validateSchema();
        $pendingCount = count(array_filter($migrations, fn($m) => !$m['applied']));
    }
}

// Verificar requisitos del sistema
$phpOk     = version_compare(PHP_VERSION, '7.4.0', '>=');
$pdoOk     = extension_loaded('pdo_mysql');
$gdOk      = extension_loaded('gd');
$jsonOk    = extension_loaded('json');
$configDir = ROOT . '/config';
$writableConfigDir = is_writable($configDir);
$requirementsOk = $phpOk && $pdoOk && $jsonOk && $writableConfigDir;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMap – Instalador / Actualizador</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #333;
            padding: 30px 16px 60px;
            line-height: 1.6;
        }
        .wrap { max-width: 820px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 32px; }
        header h1 { font-size: 1.8rem; color: #1a73e8; }
        header p  { color: #666; margin-top: 4px; }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 28px 32px;
            margin-bottom: 24px;
        }
        .card h2 { font-size: 1.1rem; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-bottom: 20px; }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: .92rem;
        }
        .alert-success { background: #e6f4ea; border-left: 4px solid #34a853; color: #1e6e35; }
        .alert-error   { background: #fce8e6; border-left: 4px solid #ea4335; color: #7d191c; }
        .alert-warning { background: #fef7e0; border-left: 4px solid #fbbc04; color: #7a5f00; }
        .alert-info    { background: #e8f0fe; border-left: 4px solid #1a73e8; color: #1a57a8; }

        .req-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .req-item { display: flex; align-items: center; gap: 8px; font-size: .9rem; padding: 6px; background: #f8f9fa; border-radius: 4px; }
        .ok  { color: #34a853; font-weight: bold; }
        .bad { color: #ea4335; font-weight: bold; }
        .warn { color: #fbbc04; font-weight: bold; }

        label { display: block; font-size: .9rem; font-weight: 600; margin-bottom: 4px; color: #555; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: .95rem;
            transition: border-color .2s;
            margin-bottom: 14px;
        }
        input[type=text]:focus, input[type=password]:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26,115,232,.15);
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        @media (max-width: 540px) { .form-grid { grid-template-columns: 1fr; } .req-grid { grid-template-columns: 1fr; } }

        .btn {
            display: inline-block;
            padding: 10px 22px;
            border: none;
            border-radius: 6px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s, opacity .2s;
        }
        .btn-primary  { background: #1a73e8; color: #fff; }
        .btn-primary:hover  { background: #1557b0; }
        .btn-success  { background: #34a853; color: #fff; }
        .btn-success:hover  { background: #267d3a; }
        .btn-warning  { background: #fbbc04; color: #333; }
        .btn-danger   { background: #ea4335; color: #fff; }
        .btn-sm       { padding: 6px 14px; font-size: .85rem; }
        .btn-block    { width: 100%; text-align: center; }

        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 9px 12px; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: .78rem;
            font-weight: 700;
        }
        .badge-ok      { background: #e6f4ea; color: #1e6e35; }
        .badge-pending { background: #fef7e0; color: #7a5f00; }
        .badge-failed  { background: #fce8e6; color: #7d191c; }

        .danger-box {
            background: #fce8e6;
            border: 2px solid #ea4335;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: .9rem;
            color: #7d191c;
        }
        .danger-box strong { display: block; font-size: 1rem; margin-bottom: 4px; }

        .step-nav { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 24px; font-size: .82rem; color: #888; }
        .step-nav span { padding: 3px 10px; border-radius: 12px; background: #e0e0e0; }
        .step-nav span.active { background: #1a73e8; color: #fff; font-weight: 700; }
        .step-nav span.done   { background: #34a853; color: #fff; }

        ul.issues { list-style: none; padding: 0; }
        ul.issues li { padding: 5px 0 5px 20px; position: relative; color: #c62828; font-size: .9rem; }
        ul.issues li::before { content: '✗'; position: absolute; left: 0; color: #ea4335; }
    </style>
</head>
<body>
<div class="wrap">

    <header>
        <h1>TravelMap – Instalador / Actualizador</h1>
        <?php
        preg_match('/\/\/\s*([\d.]+)/', file_get_contents(ROOT . '/version.php') ?: '', $_v);
        ?>
        <p>Versión de la aplicación: <?= h($_v[1] ?? '') ?></p>
        <?php if (!empty($_SESSION['installer_user'])): ?>
        <p style="margin-top:8px;font-size:.82rem;color:#888">
            Sesión como <strong><?= h($_SESSION['installer_user']) ?></strong>
            &nbsp;·&nbsp;
            <a href="index.php?installer_logout=1" style="color:#ea4335;text-decoration:none;font-weight:600">Cerrar sesión</a>
        </p>
        <?php endif; ?>
    </header>

    <!-- Advertencia de seguridad -->
    <div class="danger-box">
        <strong>⚠ Eliminar este instalador después de usarlo</strong>
        La carpeta <code>/install</code> expone operaciones de base de datos.
        Borrala o protegela con contraseña una vez que la instalación esté completa.
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>">
            <?= h($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <?php
    // ══════════════════════════════════════════════════════════════════════════
    //  PASO: done
    // ══════════════════════════════════════════════════════════════════════════
    if ($step === 'done'):
    ?>
    <div class="card">
        <h2>✅ Instalación completada</h2>
        <p style="margin-bottom:16px">La base de datos está inicializada y el usuario administrador fue creado.</p>
        <p style="margin-bottom:20px"><strong>Próximos pasos:</strong></p>
        <ol style="padding-left:20px;line-height:2">
            <li>Revisá y ajustá <code>config/config.php</code> (timezone, BASE_URL, etc.)</li>
            <li>Ingresá al panel de administración: <a href="<?= ROOT_RELATIVE_URL ?? '../admin/' ?>">../admin/</a></li>
            <li><strong>Elimina o restringe el acceso a la carpeta <code>install/</code></strong></li>
        </ol>
    </div>

    <?php
    // ══════════════════════════════════════════════════════════════════════════
    //  PASO: create_admin
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($step === 'create_admin' || ($dbConnection && $tablesExist && $userCount === 0 && $step === '')):
    ?>
    <div class="step-nav">
        <span class="done">1 Requisitos</span>
        <span class="done">2 Configuración</span>
        <span class="done">3 Base de datos</span>
        <span class="active">4 Usuario administrador</span>
        <span>5 Listo</span>
    </div>

    <div class="card">
        <h2>Crear usuario administrador</h2>
        <?php if ($userCount === 0): ?>
        <div class="alert alert-info">
            No hay usuarios en el sistema. Creá el primer administrador para poder ingresar.
        </div>
        <?php endif; ?>
        <form method="post" action="index.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_admin">
            <div class="form-grid">
                <div>
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" value="admin" required
                           minlength="3" maxlength="50" autocomplete="username">
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label for="password">Contraseña (mín. 8 caracteres)</label>
                    <input type="password" id="password" name="password" required
                           minlength="8" autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirm">Confirmar contraseña</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           minlength="8" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-success">Crear administrador</button>
        </form>
    </div>

    <?php
    // ══════════════════════════════════════════════════════════════════════════
    //  NO CONFIG: instalación nueva
    // ══════════════════════════════════════════════════════════════════════════
    elseif (!$dbPhpExists || !$configPhpExists):
    ?>
    <div class="step-nav">
        <span class="done">1 Requisitos</span>
        <span class="active">2 Configuración</span>
        <span>3 Base de datos</span>
        <span>4 Administrador</span>
        <span>5 Listo</span>
    </div>

    <!-- Requisitos del sistema -->
    <div class="card">
        <h2>Verificación de requisitos</h2>
        <div class="req-grid">
            <div class="req-item">
                <span class="<?= $phpOk ? 'ok' : 'bad' ?>"><?= $phpOk ? '✔' : '✗' ?></span>
                PHP <?= PHP_VERSION ?> <?= $phpOk ? '(≥ 7.4 ✓)' : '(se requiere ≥ 7.4)' ?>
            </div>
            <div class="req-item">
                <span class="<?= $pdoOk ? 'ok' : 'bad' ?>"><?= $pdoOk ? '✔' : '✗' ?></span>
                Extensión PDO MySQL <?= $pdoOk ? '' : '(requerida)' ?>
            </div>
            <div class="req-item">
                <span class="<?= $gdOk ? 'ok' : 'warn' ?>"><?= $gdOk ? '✔' : '!' ?></span>
                Extensión GD <?= $gdOk ? '' : '(opcional, recomendada para imágenes)' ?>
            </div>
            <div class="req-item">
                <span class="<?= $jsonOk ? 'ok' : 'bad' ?>"><?= $jsonOk ? '✔' : '✗' ?></span>
                Extensión JSON <?= $jsonOk ? '' : '(requerida)' ?>
            </div>
            <div class="req-item">
                <span class="<?= $writableConfigDir ? 'ok' : 'bad' ?>"><?= $writableConfigDir ? '✔' : '✗' ?></span>
                Directorio <code>config/</code> escribible <?= $writableConfigDir ? '' : '(verificar chmod)' ?>
            </div>
        </div>
        <?php if (!$requirementsOk): ?>
        <div class="alert alert-error" style="margin-top:16px">
            Hay requisitos no satisfechos. Corregalos antes de continuar.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($requirementsOk): ?>
    <div class="card">
        <h2>Configuración de base de datos y sitio</h2>
        <form method="post" action="index.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_config">

            <p style="font-weight:600;margin-bottom:12px;color:#555">Base de datos</p>
            <div class="form-grid">
                <div>
                    <label for="db_host">Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div>
                    <label for="db_name">Nombre de la base de datos</label>
                    <input type="text" id="db_name" name="db_name" value="travelmap" required>
                </div>
                <div>
                    <label for="db_user">Usuario MySQL</label>
                    <input type="text" id="db_user" name="db_user" value="root" required autocomplete="username">
                </div>
                <div>
                    <label for="db_pass">Contraseña MySQL</label>
                    <input type="password" id="db_pass" name="db_pass" value="" autocomplete="current-password">
                </div>
                <div>
                    <label for="db_charset">Charset</label>
                    <input type="text" id="db_charset" name="db_charset" value="utf8mb4">
                </div>
            </div>

            <p style="font-weight:600;margin-bottom:12px;color:#555">Sitio</p>
            <div>
                <label for="site_folder">Subcarpeta del proyecto en el servidor</label>
                <input type="text" id="site_folder" name="site_folder"
                       value="/TravelMap"
                       placeholder="/TravelMap  (dejar vacío si está en la raíz del dominio)">
                <small style="color:#888;font-size:.82rem">
                    Ejemplos: <code>/TravelMap</code> (XAMPP local) · <code>/</code> o vacío (raíz del dominio)
                </small>
            </div>
            <br>
            <button type="submit" class="btn btn-primary" <?= $requirementsOk ? '' : 'disabled' ?>>
                Guardar configuración y probar conexión
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php
    // ══════════════════════════════════════════════════════════════════════════
    //  CONFIG OK, sin tablas: ofrecer inicializar BD
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($dbConnection && !$tablesExist):
    ?>
    <div class="step-nav">
        <span class="done">1 Requisitos</span>
        <span class="done">2 Configuración</span>
        <span class="active">3 Base de datos</span>
        <span>4 Administrador</span>
        <span>5 Listo</span>
    </div>

    <div class="card">
        <h2>Inicializar base de datos</h2>
        <div class="alert alert-info">
            La conexión es correcta pero no se encontraron tablas.
            Se ejecutará <code>database.sql</code> para crear la estructura completa.
        </div>
        <form method="post" action="index.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="init_db">
            <button type="submit" class="btn btn-success">
                Crear tablas e insertar datos iniciales
            </button>
        </form>
    </div>

    <?php
    // ══════════════════════════════════════════════════════════════════════════
    //  CONFIG OK + TABLAS: modo actualización
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($dbError):
    ?>
    <div class="card">
        <h2>Error de conexión</h2>
        <div class="alert alert-error">
            <?= h($dbError) ?>
        </div>
        <p>Revisá las credenciales en <code>config/db.php</code> o eliminá el archivo para volver al asistente.</p>
    </div>

    <?php else: ?>

    <!-- Modo actualización / mantenimiento -->

    <?php if ($schemaIssues): ?>
    <div class="card">
        <h2>⚠ Problemas en el esquema</h2>
        <ul class="issues">
            <?php foreach ($schemaIssues as $issue): ?>
            <li><?= h($issue) ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin-top:12px;font-size:.9rem;color:#666">
            Ejecutá las migraciones pendientes para resolver estos problemas.
        </p>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        ✔ El esquema de la base de datos está en orden.
    </div>
    <?php endif; ?>

    <!-- Resultados de la última ejecución -->
    <?php if ($migrationResults): ?>
    <div class="card">
        <h2>Resultado de la última ejecución</h2>
        <table>
            <thead><tr><th>ID</th><th>Descripción</th><th>Estado</th><th>Mensaje</th></tr></thead>
            <tbody>
            <?php foreach ($migrationResults as $r): ?>
            <tr>
                <td><code><?= h($r['id']) ?></code></td>
                <td><?= h($r['description']) ?></td>
                <td>
                    <?php if ($r['skipped']): ?>
                        <span class="badge badge-ok">Ya aplicada</span>
                    <?php elseif ($r['success']): ?>
                        <span class="badge badge-ok">✔ Aplicada</span>
                    <?php else: ?>
                        <span class="badge badge-failed">✗ Error</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.85rem;color:#666"><?= h($r['message']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Estado de migraciones -->
    <div class="card">
        <h2>Migraciones de base de datos</h2>
        <?php if ($pendingCount > 0): ?>
        <div class="alert alert-warning">
            Hay <strong><?= $pendingCount ?></strong> migración<?= $pendingCount > 1 ? 'es' : '' ?> pendiente<?= $pendingCount > 1 ? 's' : '' ?>.
        </div>
        <form method="post" action="index.php" style="margin-bottom:16px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="run_migrations">
            <button type="submit" class="btn btn-warning">
                Ejecutar <?= $pendingCount ?> migración<?= $pendingCount > 1 ? 'es' : '' ?> pendiente<?= $pendingCount > 1 ? 's' : '' ?>
            </button>
        </form>
        <?php else: ?>
        <div class="alert alert-success">✔ Todas las migraciones están aplicadas.</div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($migrations as $i => $m): ?>
            <tr>
                <td style="color:#999;font-size:.85rem"><?= $i + 1 ?></td>
                <td><code style="font-size:.82rem"><?= h($m['id']) ?></code></td>
                <td><?= h($m['description']) ?></td>
                <td>
                    <?php if ($m['applied']): ?>
                        <span class="badge badge-ok">✔ Aplicada</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pendiente</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Crear usuario adicional -->
    <div class="card">
        <h2>Gestión de usuarios</h2>
        <p style="margin-bottom:16px;font-size:.9rem">
            Usuarios registrados: <strong><?= $userCount ?></strong>
        </p>
        <details>
            <summary style="cursor:pointer;font-weight:600;color:#1a73e8;font-size:.9rem">
                + Crear usuario adicional
            </summary>
            <form method="post" action="index.php" style="margin-top:16px">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_admin">
                <div class="form-grid">
                    <div>
                        <label for="new_username">Usuario</label>
                        <input type="text" id="new_username" name="username" required
                               minlength="3" maxlength="50" autocomplete="username">
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label for="new_password">Contraseña (mín. 8 caracteres)</label>
                        <input type="password" id="new_password" name="password" required
                               minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="new_password_confirm">Confirmar contraseña</label>
                        <input type="password" id="new_password_confirm" name="password_confirm" required
                               minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Crear usuario</button>
            </form>
        </details>
    </div>

    <?php endif; ?>

</div><!-- /.wrap -->
</body>
</html>
