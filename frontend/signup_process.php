<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/db.php';

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
    // Check for existing user across both employee and admin roles
    $existingByEmail = db_mysql_find_user($email, 'employee') ?? db_mysql_find_user($email, 'admin');
    $existingById = db_mysql_find_user($employee_id, 'employee') ?? db_mysql_find_user($employee_id, 'admin');

    if ($existingByEmail || $existingById) {
        header('Location: signup.php?error=2');
        exit;
    }

    // Determine requested role; allow selecting admin without a code
    $role = in_array($role, ['employee', 'admin'], true) ? $role : 'employee';

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Hosted users schema splits name into first/last and stores the
    // password under `password` (not password_hash). role is the enum
    // staff/manager/admin — the app's employee maps to staff, admin maps
    // to admin. rfid_uid is NOT NULL + UNIQUE, so app-created accounts get
    // a deterministic synthetic value derived from employee_id.
    $nameParts = array_values(array_filter(array_map('trim', explode(' ', $name))));
    $firstName = $nameParts[0] ?? 'Unknown';
    $lastName  = $nameParts[1] ?? '';
    $hostedRole = $role === 'admin' ? 'admin' : 'staff';
    $rfidUid    = 'app-' . $employee_id;

    $pdo = db_mysql_connect();
    $stmt = $pdo->prepare('INSERT INTO users (employee_id, rfid_uid, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$employee_id, $rfidUid, $firstName, $lastName, $email, $passwordHash, $hostedRole]);
} catch (Throwable $e) {
    error_log('Signup failed: ' . $e->getMessage());
    header('Location: signup.php?error=3');
    exit;
}

header('Location: signup.php?success=1');
exit;
