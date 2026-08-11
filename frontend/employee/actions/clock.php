<?php
// clock.php — POST endpoint: toggles the logged-in employee's clock
// state via the backend API. Full-reload pattern (redirect back to
// portal.php).
require_once __DIR__ . '/../../auth.php';
require_role('employee');
require_once __DIR__ . '/../../lib/api.php';

$type = ($_POST['type'] ?? 'in') === 'out' ? 'out' : 'in';

try {
    [$status, $body] = api_post('/clock/toggle', ['action' => $type]);
} catch (Throwable $e) {
    error_log('Clock API error: ' . $e->getMessage());
}

header('Location: ../portal.php');
exit;