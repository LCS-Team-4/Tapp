<?php
// SECURITY: This debug script is disabled in production. It exposes
// API internals — it must never be accessible to unauthenticated users.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once 'frontend/lib/api.php';
$_SERVER['SCRIPT_NAME'] = '/Tapp/frontend/login_process.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "BASE_URL=" . api_base_url() . "\n";
try {
    list($status, $body) = api_post('/auth/login', ['login_id' => 'sami@gmail.com', 'password' => 'wrongpass']);
    echo "STATUS=$status\n";
    echo json_encode($body, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}