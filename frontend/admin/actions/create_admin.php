<?php
// create_admin.php — admin-only endpoint to create another admin account
require_once __DIR__ . '/../../auth.php';
require_role('admin');
require_once __DIR__ . '/../../lib/db.php';

$name = trim($_POST['name'] ?? '');
$employee_id = trim($_POST['employee_id'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');

if ($name === '' || $employee_id === '' || $email === '' || $password === '' || $passwordConfirm === '' || $password !== $passwordConfirm) {
    header('Location: ../portal.php?admin_create_error=1');
    exit;
}

try {
    // Check duplicates across admin and employee roles
    $existingByEmail = db_mysql_find_user($email, 'employee') ?? db_mysql_find_user($email, 'admin');
    $existingById = db_mysql_find_user($employee_id, 'employee') ?? db_mysql_find_user($employee_id, 'admin');

    if ($existingByEmail || $existingById) {
        header('Location: ../portal.php?admin_create_error=2');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Hosted users schema: split name, store password under `password`,
    // role enum staff/manager/admin. rfid_uid is NOT NULL + UNIQUE, so
    // app-created admin accounts get a synthetic value from employee_id.
    $nameParts = array_values(array_filter(array_map('trim', explode(' ', $name))));
    $firstName = $nameParts[0] ?? 'Unknown';
    $lastName  = $nameParts[1] ?? '';
    $rfidUid   = 'app-' . $employee_id;

    $pdo = db_mysql_connect();
    $stmt = $pdo->prepare('INSERT INTO users (employee_id, rfid_uid, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$employee_id, $rfidUid, $firstName, $lastName, $email, $passwordHash, 'admin']);
} catch (Throwable $e) {
    error_log('Create admin failed: ' . $e->getMessage());
    header('Location: ../portal.php?admin_create_error=3');
    exit;
}

header('Location: ../portal.php?admin_create_success=1');
exit;
