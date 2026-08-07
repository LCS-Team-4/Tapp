<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/api.php';

$name = trim($_POST['name'] ?? '');
$employee_id = trim($_POST['employee_id'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');
$role = trim($_POST['role'] ?? 'employee');

if ($name === '' || $employee_id === '' || $email === '' || $password === '' || $passwordConfirm === '' || $password !== $passwordConfirm) {
    header('Location: signup.php?error=1');
    exit;
}

try {
    [$status, $body] = api_post('/auth/signup', [
        'name' => $name,
        'employee_id' => $employee_id,
        'email' => $email,
        'password' => $password,
        'password_confirm' => $passwordConfirm,
        'role' => $role,
    ]);
} catch (Throwable $e) {
    error_log('Signup API error: ' . $e->getMessage());
    header('Location: signup.php?error=3');
    exit;
}

if ($status === 409) {
    header('Location: signup.php?error=2');
    exit;
}

if ($status === 400) {
    header('Location: signup.php?error=1');
    exit;
}

if ($status !== 201) {
    header('Location: signup.php?error=3');
    exit;
}

header('Location: signup.php?success=1');
exit;