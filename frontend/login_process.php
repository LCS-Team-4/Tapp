<?php
/**
 * Login handler — authenticates against the backend UserRepository directly
 * (same process) so local PHP built-in server setups work without needing
 * a working HTTP loopback to /backend/public.
 */
require_once __DIR__ . '/auth.php';

$role     = $_POST['role'] ?? 'employee';
$loginId  = trim($_POST['login_id'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($role !== 'admin') {
    $role = 'employee';
}

if ($loginId === '' || $password === '') {
    header('Location: login.php?error=1');
    exit;
}

$_SESSION['login_debug'] = [
    'login_id'     => $loginId,
    'role'         => $role,
    'request_uri'  => $_SERVER['REQUEST_URI'] ?? '',
    'script_name'  => $_SERVER['SCRIPT_NAME'] ?? '',
    'started_at'   => date('c'),
    'mode'         => 'direct',
];

// ---------------------------------------------------------------------------
// Direct backend auth (no HTTP self-request)
// ---------------------------------------------------------------------------
$backendRoot = dirname(__DIR__) . '/backend';
$autoload    = $backendRoot . '/vendor/autoload.php';
$bootstrap   = $backendRoot . '/bootstrap/app.php';

try {
    if (!is_file($autoload)) {
        throw new RuntimeException('Backend autoload not found at ' . $autoload);
    }
    require_once $autoload;

    // Load env + config the same way the API does.
    if (is_file($bootstrap) && !function_exists('config')) {
        require $bootstrap;
    }

    $users = new \App\Repositories\UserRepository();
    $user  = $users->verifyCredentials($loginId, $password);
} catch (\App\Exceptions\AuthException $e) {
    $_SESSION['login_debug']['exception'] = $e->getMessage();
    header('Location: login.php?error=3');
    exit;
} catch (Throwable $e) {
    $_SESSION['login_debug']['exception'] = $e->getMessage();
    $_SESSION['login_debug']['file'] = $e->getFile() . ':' . $e->getLine();
    error_log('Login error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    header('Location: login.php?error=2');
    exit;
}

$userArray = $user->toArray();

// Enforce the role the user selected on the login form.
$actualRole = $userArray['role'] ?? 'employee';
if ($role === 'admin' && $actualRole !== 'admin') {
    $_SESSION['login_debug']['exception'] = 'Not an admin account';
    header('Location: login.php?error=3');
    exit;
}

// Establish frontend session.
$_SESSION['authenticated'] = true;
$_SESSION['user_id']       = $userArray['id'] ?? null;
$_SESSION['role']          = $actualRole;
$_SESSION['user_name']     = $userArray['name'] ?? '';
$_SESSION['employee_id']   = $userArray['employee_id'] ?? '';
$_SESSION['initials']      = compute_initials($userArray['name'] ?? '');
$_SESSION['role_label']    = $actualRole === 'admin' ? 'System Admin' : 'Employee';

if ($actualRole === 'admin' && $role === 'admin') {
    header('Location: admin/portal.php');
} else {
    header('Location: employee/portal.php');
}
exit;

function compute_initials(string $name): string
{
    $parts = array_filter(array_map('trim', explode(' ', $name)));
    if (count($parts) === 0) {
        return '??';
    }
    return strtoupper(implode('', array_map(fn ($p) => $p[0], $parts)));
}
