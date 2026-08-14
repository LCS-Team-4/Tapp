<?php
require_once __DIR__ . '/auth.php';

$employeeId  = trim($_POST['employee_id'] ?? '');
$email       = trim($_POST['email'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');

if ($employeeId === '' || $email === '' || $newPassword === '' || strlen($newPassword) < 8) {
    header('Location: forgot_password.php?error=1');
    exit;
}

$backendRoot = dirname(__DIR__) . '/backend';
$autoload    = $backendRoot . '/vendor/autoload.php';
$bootstrap   = $backendRoot . '/bootstrap/app.php';

try {
    if (!is_file($autoload)) {
        throw new RuntimeException('Backend autoload not found');
    }
    require_once $autoload;
    if (!function_exists('config') && is_file($bootstrap)) {
        require $bootstrap;
    }

    $pdo = \App\Database\Connection::get();

    // Find matching employee (must match both employee_id and email)
    $stmt = $pdo->prepare(
        'SELECT id, employee_id, email, role FROM users WHERE employee_id = ? AND email = ? LIMIT 1'
    );
    $stmt->execute([$employeeId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: forgot_password.php?error=2');
        exit;
    }

    // Do not allow password reset for admin accounts via this public form
    $role = $row['role'] ?? '';
    if ($role === 'admin' || $role === 'manager') {
        header('Location: forgot_password.php?error=2');
        exit;
    }

    // Ensure table exists (self-heal if migration not run)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_requests (
          id int unsigned NOT NULL AUTO_INCREMENT,
          user_id int unsigned NOT NULL,
          employee_id varchar(20) NOT NULL,
          email varchar(150) NOT NULL,
          new_password_hash varchar(255) NOT NULL,
          status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          reviewed_at timestamp NULL DEFAULT NULL,
          reviewed_by int unsigned DEFAULT NULL,
          PRIMARY KEY (id),
          KEY idx_prr_status (status),
          KEY idx_prr_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Block duplicate pending requests
    $check = $pdo->prepare(
        "SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1"
    );
    $check->execute([(int) $row['id']]);
    if ($check->fetch()) {
        header('Location: forgot_password.php?error=4');
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $ins = $pdo->prepare(
        'INSERT INTO password_reset_requests (user_id, employee_id, email, new_password_hash, status)
         VALUES (?, ?, ?, ?, \'pending\')'
    );
    $ins->execute([(int) $row['id'], $row['employee_id'], $row['email'], $hash]);

    header('Location: forgot_password.php?success=1');
    exit;
} catch (Throwable $e) {
    error_log('forgot_password_process: ' . $e->getMessage());
    header('Location: forgot_password.php?error=3');
    exit;
}
