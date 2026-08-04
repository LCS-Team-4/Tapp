<?php
// leave.php — POST endpoint: submit/edit/cancel the logged-in employee's
// own leave requests in db.json. Full-reload pattern, same as clock.php.
require_once __DIR__ . '/../../auth.php';
require_role('employee');
require_once __DIR__ . '/../../lib/db.php';

function leave_duration_days(string $startDate, string $endDate): int
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end   = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$start || !$end) {
        return 1;
    }
    return $start->diff($end)->days + 1;
}

$validLeaveTypes = ['annual', 'sick', 'unpaid', 'emergency'];

$employeeId = $_SESSION['employee_id'];
$action     = $_POST['action'] ?? '';
$employee   = db_employee($employeeId);

$db = db_read();
$db['leave_requests'] = $db['leave_requests'] ?? [];

$logEntry = null;

if ($action === 'submit' || $action === 'edit') {
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    $valid = in_array($leaveType, $validLeaveTypes, true)
        && $startDate !== ''
        && $endDate !== ''
        && $reason !== '';

    if ($valid && $action === 'submit') {
        $nextId = 0;
        foreach ($db['leave_requests'] as $req) {
            $nextId = max($nextId, $req['leave_id']);
        }
        $db['leave_requests'][] = [
            'leave_id'      => $nextId + 1,
            'employee_id'   => $employeeId,
            'employee_name' => $employee['name'] ?? '',
            'leave_type'    => $leaveType,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'duration_days' => leave_duration_days($startDate, $endDate),
            'reason'        => $reason,
            'status'        => 'pending',
            'decided_at'    => null,
        ];
        $logEntry = [
            'time_label'    => date('H:i'),
            'employee_name' => $employee['name'] ?? '',
            'event_type'    => 'leave_applied',
        ];
    } elseif ($valid) { // edit
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        foreach ($db['leave_requests'] as &$req) {
            if ($req['leave_id'] === $leaveId && $req['employee_id'] === $employeeId && $req['status'] === 'pending') {
                $req['leave_type']    = $leaveType;
                $req['start_date']    = $startDate;
                $req['end_date']      = $endDate;
                $req['duration_days'] = leave_duration_days($startDate, $endDate);
                $req['reason']        = $reason;
            }
        }
        unset($req);
    }
} elseif ($action === 'cancel') {
    $leaveId = (int) ($_POST['leave_id'] ?? 0);
    $db['leave_requests'] = array_values(array_filter(
        $db['leave_requests'],
        fn ($req) => !($req['leave_id'] === $leaveId && $req['employee_id'] === $employeeId && $req['status'] === 'pending')
    ));
}

db_write($db);
if ($logEntry) {
    db_log_activity($logEntry);
}

header('Location: ../portal.php');
exit;
