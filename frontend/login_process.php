<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';

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

try {
    $user = db_find_user($loginId, $role);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: login.php?error=2');
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: login.php?error=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['role']          = $user['role'];
$_SESSION['user_name']     = $user['name'];
$_SESSION['employee_id']   = $user['employee_id'];
$_SESSION['initials']      = db_mysql_compute_initials($user['name']);
$_SESSION['role_label']    = $user['role'] === 'admin'
    ? 'System Admin'
    : ($user['department'] ? $user['department'] . ' · ' : '') . $user['employee_id'];

if ($user['role'] === 'admin') {
    header('Location: admin/portal.php');
} else {
    header('Location: employee/portal.php');
}
exit;
