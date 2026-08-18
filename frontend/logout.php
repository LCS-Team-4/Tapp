<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/api.php';

// Call the backend logout endpoint first so the sign-out event can be
// logged to Google Sheets (if sync is enabled). The backend reads the
// employee identity from the shared session before destroying it.
try {
    api_post('/auth/logout');
} catch (Throwable $e) {
    error_log('Logout API error: ' . $e->getMessage());
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;