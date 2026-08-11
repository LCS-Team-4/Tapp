<?php
// leave.php — POST endpoint: submit/edit/cancel the logged-in employee's
// own leave requests via the backend API. Full-reload pattern.
require_once __DIR__ . '/../../auth.php';
require_role('employee');
require_once __DIR__ . '/../../lib/api.php';

$action = $_POST['action'] ?? '';

if ($action === 'submit' || $action === 'edit') {
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    $payload = [
        'leave_type' => $leaveType,
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'reason'     => $reason,
    ];

    try {
        if ($action === 'submit') {
            api_post('/leave-request', $payload);
        } else {
            $leaveId = (int) ($_POST['leave_id'] ?? 0);
            api_put('/leave-requests/' . $leaveId, $payload);
        }
    } catch (Throwable $e) {
        error_log('Leave API error: ' . $e->getMessage());
    }
} elseif ($action === 'cancel') {
    $leaveId = (int) ($_POST['leave_id'] ?? 0);
    try {
        api_delete('/leave-requests/' . $leaveId);
    } catch (Throwable $e) {
        error_log('Leave cancel API error: ' . $e->getMessage());
    }
}

header('Location: ../portal.php');
exit;