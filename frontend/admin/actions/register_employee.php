<?php
/**
 * Register a new employee (admin only).
 * Runs in-process against the backend repository so it works with the
 * PHP built-in server without needing HTTP loopback to /backend/public.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth.php';

if (empty($_SESSION['authenticated']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Unauthorized — please log in as admin again']]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$name       = trim((string) ($payload['name'] ?? ''));
$email      = trim((string) ($payload['email'] ?? ''));
$department = trim((string) ($payload['department'] ?? '')) ?: null;
$position   = trim((string) ($payload['position'] ?? '')) ?: null;

if ($name === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Name and email are required']]);
    exit;
}

// frontend/admin/actions -> Tapp root is three levels up
$backendRoot = dirname(__DIR__, 3) . '/backend';
$autoload    = $backendRoot . '/vendor/autoload.php';
$bootstrap   = $backendRoot . '/bootstrap/app.php';

try {
    if (!is_file($autoload)) {
        throw new RuntimeException('Backend autoload not found at ' . $autoload);
    }
    require_once $autoload;

    if (!function_exists('config')) {
        if (!is_file($bootstrap)) {
            throw new RuntimeException('Backend bootstrap not found at ' . $bootstrap);
        }
        require $bootstrap;
    }

    $users = new \App\Repositories\UserRepository();

    if ($users->emailExists($email)) {
        http_response_code(409);
        echo json_encode(['error' => ['message' => 'Email already in use']]);
        exit;
    }

    // Auto-generate strong temporary password (admin does not set it)
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghijkmnopqrstuvwxyz';
    $numbers = '23456789';
    $symbols = '!@#$%&*';
    $all     = $upper . $lower . $numbers . $symbols;
    $temporaryPassword  = $upper[random_int(0, strlen($upper) - 1)];
    $temporaryPassword .= $lower[random_int(0, strlen($lower) - 1)];
    $temporaryPassword .= $numbers[random_int(0, strlen($numbers) - 1)];
    $temporaryPassword .= $symbols[random_int(0, strlen($symbols) - 1)];
    for ($i = strlen($temporaryPassword); $i < 12; $i++) {
        $temporaryPassword .= $all[random_int(0, strlen($all) - 1)];
    }
    $temporaryPassword = str_shuffle($temporaryPassword);

    $nameParts = array_values(array_filter(array_map('trim', explode(' ', $name))));
    $firstName = $nameParts[0] ?? 'Unknown';
    $lastName  = implode(' ', array_slice($nameParts, 1));

    $counter = 1;
    do {
        $employeeId = sprintf('S-%03d', $counter++);
    } while ($users->employeeIdExists($employeeId));

    $created = $users->create(
        $employeeId,
        $firstName,
        $lastName,
        $email,
        password_hash($temporaryPassword, PASSWORD_BCRYPT),
        'staff',
        $department,
        $position,
        true
    );

    http_response_code(201);
    echo json_encode([
        'data' => [
            'employee_id'         => $created->employeeId,
            'name'                => $created->name,
            'email'               => $created->email,
            'department'          => $department,
            'position'            => $position,
            'temporary_password'  => $temporaryPassword,
        ],
        'temporary_password' => $temporaryPassword,
    ]);
} catch (Throwable $e) {
    error_log('register_employee: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => [
            'message' => 'Unable to register employee: ' . $e->getMessage(),
        ],
    ]);
}
