<?php
/**
 * Approve or reject a pending password reset request.
 * On approve: apply new_password_hash to users.password and clear must_change_password.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../auth.php';

if (empty($_SESSION['authenticated']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$requestId = (int) ($payload['request_id'] ?? 0);
$decision  = strtolower(trim((string) ($payload['decision'] ?? '')));

if ($requestId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid request']]);
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
    $adminId = (int) ($_SESSION['user_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        http_response_code(404);
        echo json_encode(['error' => ['message' => 'Pending request not found']]);
        exit;
    }

    if ($decision === 'approved') {
        // Apply the new password to the user account
        try {
            $upd = $pdo->prepare(
                'UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?'
            );
            $upd->execute([$req['new_password_hash'], (int) $req['user_id']]);
        } catch (PDOException $e) {
            // Column may not exist
            $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->execute([$req['new_password_hash'], (int) $req['user_id']]);
        }
    }

    $rev = $pdo->prepare(
        'UPDATE password_reset_requests
         SET status = ?, reviewed_at = NOW(), reviewed_by = ?
         WHERE id = ?'
    );
    $rev->execute([$decision, $adminId > 0 ? $adminId : null, $requestId]);

    echo json_encode([
        'data' => [
            'request_id' => $requestId,
            'status' => $decision,
            'message' => $decision === 'approved'
                ? 'Password reset approved. Employee can now log in with the new password.'
                : 'Password reset request rejected.',
        ],
    ]);
} catch (Throwable $e) {
    error_log('password_reset_decision: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
}
