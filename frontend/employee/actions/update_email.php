<?php
/**
 * Update the logged-in employee's contact email only.
 * Full name, employee ID and department are not changeable here.
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

$email = trim((string) ($payload['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'A valid email address is required']]);
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

    // Reject if email is already used by someone else
    $existing = $users->findByLoginId($email);
    if ($existing !== null && $existing->id !== $userId) {
        http_response_code(409);
        echo json_encode(['error' => ['message' => 'Email already in use']]);
        exit;
    }

    $updated = $users->updateEmailById($userId, $email);
    if ($updated === null) {
        http_response_code(500);
        echo json_encode(['error' => ['message' => 'Unable to update email']]);
        exit;
    }

    echo json_encode([
        'data' => [
            'email' => $updated->email,
            'message' => 'Contact details updated',
        ],
    ]);
} catch (Throwable $e) {
    error_log('update_email: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Unable to update email: ' . $e->getMessage()]]);
}
