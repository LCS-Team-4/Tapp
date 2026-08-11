<?php
// leave_decision.php — POST endpoint: approve/decline a pending leave
// request via the backend API. Full-reload pattern.
require_once __DIR__ . '/../../auth.php';
require_role('admin');
require_once __DIR__ . '/../../lib/api.php';

$leaveId  = (int) ($_POST['leave_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (in_array($decision, ['approved', 'declined'], true) && $leaveId > 0) {
    try {
        api_put('/admin/leave-requests/' . $leaveId, ['status' => $decision]);
    } catch (Throwable $e) {
        error_log('Leave decision API error: ' . $e->getMessage());
    }
}

header('Location: ../portal.php');
exit;