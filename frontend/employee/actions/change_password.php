<?php
/**
 * Change the logged-in employee's password and clear must_change_password.
 * Used by the first-login popup and the profile "Update Password" form.
 * Writes directly to the database (no HTTP loopback to the API).
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth.php';

if (empty($_SESSION['authenticated']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Unauthorized — please log in again']]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$currentPassword = (string) ($payload['current_password'] ?? '');
$newPassword     = (string) ($payload['new_password'] ?? '');
$confirmPassword = (string) ($payload['confirm_password'] ?? '');

if ($newPassword === '' || strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'New password must be at least 8 characters']]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'New password and confirmation do not match']]);
    exit;
}

$backendRoot = dirname(__DIR__, 3) . '/backend';
$autoload    = $backendRoot . '/vendor/autoload.php';
$bootstrap   = $backendRoot . '/bootstrap/app.php';

try {
    if (!is_file($autoload)) {
        throw new RuntimeException('Backend autoload not found');
    }
    require_once $autoload;

    if (!function_exists('config')) {
        if (!is_file($bootstrap)) {
            throw new RuntimeException('Backend bootstrap not found');
        }
        require $bootstrap;
    }

    $users = new \App\Repositories\UserRepository();
    $userId = (int) $_SESSION['user_id'];
    $employeeId = (string) ($_SESSION['employee_id'] ?? '');
    $email = '';

    // Load current user to get email for credential check
    $user = $users->findById($userId);
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'User not found']]);
        exit;
    }
    $email = $user->email;
    $employeeId = $user->employeeId;

    // Verify current (temporary or existing) password
    try {
        $users->verifyCredentials($email !== '' ? $email : $employeeId, $currentPassword);
    } catch (\App\Exceptions\AuthException) {
        try {
            $users->verifyCredentials($employeeId, $currentPassword);
        } catch (\App\Exceptions\AuthException) {
            http_response_code(401);
            echo json_encode(['error' => ['message' => 'Current password is incorrect']]);
            exit;
        }
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $ok = $users->changePassword($userId, $hash);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => ['message' => 'Unable to update password in database']]);
        exit;
    }

    echo json_encode([
        'data' => [
            'message' => 'Password updated successfully',
            'must_change_password' => false,
        ],
    ]);
} catch (Throwable $e) {
    error_log('change_password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Unable to update password: ' . $e->getMessage()]]);
}
