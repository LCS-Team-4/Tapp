<?php
// SECURITY: This debug script is disabled in production. It exposes
// API internals — it must never be accessible to unauthenticated users.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$_SERVER['SCRIPT_NAME'] = '/Tapp/frontend/login_process.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_METHOD'] = 'POST';

session_start();
require __DIR__ . '/lib/api.php';

try {
    echo 'API_BASE_URL=' . api_base_url() . PHP_EOL;
    [$status, $body] = api_post('/auth/login', ['login_id' => 'sami@gmail.com', 'password' => 'wrongpass']);
    echo 'STATUS=' . $status . PHP_EOL;
    echo 'BODY=' . json_encode($body, JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo 'EXCEPTION=' . $e->getMessage() . PHP_EOL;
}