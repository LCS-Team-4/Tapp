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

// Demo-only credential check: every seed user in data/db.json shares the
// password "Password123!" (see db.json's top-level comment) — this is
// mock auth for a prototype, not a real login system.
$user = db_find_user($loginId, $role);
if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: login.php?error=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['role']          = $user['role'];
$_SESSION['user_name']     = $user['name'];
$_SESSION['employee_id']   = $user['id'];
$_SESSION['initials']      = $user['initials'];
$_SESSION['role_label']    = $user['role'] === 'admin'
    ? 'System Admin'
    : $user['department'] . ' · ' . $user['id'];

if ($user['role'] === 'admin') {
    header('Location: admin/portal.php');
} else {
    header('Location: employee/portal.php');
}
exit;
