<?php
// leave_decision.php — POST endpoint: approve/decline a pending leave
// request in db.json. Full-reload pattern, same as the employee actions.
require_once __DIR__ . '/../../auth.php';
require_role('admin');
require_once __DIR__ . '/../../lib/db.php';

$leaveId  = (int) ($_POST['leave_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (in_array($decision, ['approved', 'declined'], true)) {
    $db = db_read();
    $db['leave_requests'] = $db['leave_requests'] ?? [];
    $decidedEmployeeName = null;

    foreach ($db['leave_requests'] as &$req) {
        if ($req['leave_id'] === $leaveId && $req['status'] === 'pending') {
            $req['status']        = $decision;
            $req['decided_at']    = date('Y-m-d');
            $decidedEmployeeName  = $req['employee_name'];
        }
    }
    unset($req);

    db_write($db);

    if ($decidedEmployeeName !== null) {
        db_log_activity([
            'time_label'    => date('H:i'),
            'employee_name' => $decidedEmployeeName,
            'event_type'    => 'leave_decided',
        ]);
    }
}

header('Location: ../portal.php');
exit;
