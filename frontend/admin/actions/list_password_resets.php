<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth.php';

if (empty($_SESSION['authenticated']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

$backendRoot = dirname(__DIR__, 3) . '/backend';
$autoload    = $backendRoot . '/vendor/autoload.php';
$bootstrap   = $backendRoot . '/bootstrap/app.php';

try {
    require_once $autoload;
    if (!function_exists('config') && is_file($bootstrap)) {
        require $bootstrap;
    }
    $pdo = \App\Database\Connection::get();

    // Ensure table exists
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

    $stmt = $pdo->query(
        "SELECT id, employee_id, email, status, created_at, reviewed_at
         FROM password_reset_requests
         ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC
         LIMIT 100"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => ['requests' => $rows]]);
} catch (Throwable $e) {
    error_log('list_password_resets: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
}
