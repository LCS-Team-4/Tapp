<?php
// create_admin.php — admin-only endpoint to create another admin account
// via the backend API. Uses the secure /api/admin/admins/invite endpoint
// (admin-only) instead of the public /auth/signup endpoint, which no longer
// accepts a role parameter.
require_once __DIR__ . '/../../auth.php';
require_role('admin');
require_once __DIR__ . '/../../lib/api.php';

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');

if ($name === '' || $email === '' || $password === '' || $passwordConfirm === '' || $password !== $passwordConfirm) {
    header('Location: ../portal.php?admin_create_error=1');
    exit;
}

try {
    [$status, $body] = api_post('/admin/admins/invite', [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirm' => $passwordConfirm,
    ]);
} catch (Throwable $e) {
    error_log('Create admin API error: ' . $e->getMessage());
    header('Location: ../portal.php?admin_create_error=3');
    exit;
}

if ($status === 409) {
    header('Location: ../portal.php?admin_create_error=2');
    exit;
}

if ($status !== 201) {
    header('Location: ../portal.php?admin_create_error=3');
    exit;
}

header('Location: ../portal.php?admin_create_success=1');
exit;