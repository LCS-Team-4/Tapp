<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/api.php';

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
    'login_id'      => $loginId,
    'role'          => $role,
    'request_uri'   => $_SERVER['REQUEST_URI'] ?? '',
    'script_name'   => $_SERVER['SCRIPT_NAME'] ?? '',
    'api_base_url'  => api_base_url(),
    'started_at'    => date('c'),
];

try {
    [$status, $body] = api_post('/auth/login', [
        'login_id' => $loginId,
        'password' => $password,
    ]);
    $_SESSION['login_debug']['api_status'] = $status;
    $_SESSION['login_debug']['api_body'] = $body;
} catch (Throwable $e) {
    $_SESSION['login_debug']['exception'] = $e->getMessage();
    error_log('Login API error: ' . $e->getMessage());
    header('Location: login.php?error=2');
    exit;
}

if ($status === 401) {
    header('Location: login.php?error=3');
    exit;
}

if ($status !== 200) {
    error_log('Login failure: status=' . $status . ' body=' . json_encode($body));
    header('Location: login.php?error=2');
    exit;
}

$user = api_data($body);

// Note: do NOT call session_regenerate_id() here — the backend's
// AuthController::login() already started the session and set
// $_SESSION['user_id'] under the current session ID. Regenerating would
// orphan that data and break subsequent authenticated API calls.
$_SESSION['authenticated'] = true;
$_SESSION['role']          = $user['role'] ?? 'employee';
$_SESSION['user_name']     = $user['name'] ?? '';
$_SESSION['employee_id']   = $user['employee_id'] ?? '';
$_SESSION['initials']      = compute_initials($user['name'] ?? '');
$_SESSION['role_label']    = ($user['role'] ?? '') === 'admin'
    ? 'System Admin'
    : ($user['employee_id'] ?? '');

if (($user['role'] ?? '') === 'admin') {
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