<?php
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

/*
 * ---------------------------------------------------------------
 * DEMO AUTH ONLY.
 * Replace this block with a real lookup, e.g.:
 *
 *   $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
 *   $stmt->execute([$loginId, $role]);
 *   $user = $stmt->fetch();
 *   if (!$user || !password_verify($password, $user['password_hash'])) {
 *       header('Location: login.php?error=1');
 *       exit;
 *   }
 * ---------------------------------------------------------------
 */

session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['role']          = $role;

if ($role === 'admin') {
    $_SESSION['user_name']   = 'Amara Osei';
    $_SESSION['role_label']  = 'System Admin';
    $_SESSION['employee_id'] = 'ADM-0001';
    $_SESSION['initials']    = 'AO';
    header('Location: admin/portal.php');
} else {
    $_SESSION['user_name']   = 'Sarah Lee';
    $_SESSION['role_label']  = 'Design · EMP-0142';
    $_SESSION['employee_id'] = 'EMP-0142';
    $_SESSION['initials']    = 'SL';
    header('Location: employee/portal.php');
}
exit;
