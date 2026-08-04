<?php
// clock.php — POST endpoint: toggles the logged-in employee's today
// attendance in db.json. Full-reload pattern (redirect back to
// portal.php) rather than an AJAX response — matches how login already
// works, and portal.php re-renders correctly from db.json either way.
require_once __DIR__ . '/../../auth.php';
require_role('employee');
require_once __DIR__ . '/../../lib/db.php';

// Real elapsed hours between two "h:i A"-formatted clock times, used when
// clocking out. Returns null if either time is missing/unparseable rather
// than showing a misleading 0.
function clock_hours(?string $clockIn, string $clockOut): ?float
{
    if (!$clockIn) {
        return null;
    }
    $in  = DateTime::createFromFormat('h:i A', $clockIn);
    $out = DateTime::createFromFormat('h:i A', $clockOut);
    if (!$in || !$out) {
        return null;
    }
    $seconds = $out->getTimestamp() - $in->getTimestamp();
    return $seconds >= 0 ? round($seconds / 3600, 1) : null;
}

$employeeId = $_SESSION['employee_id'];
$type       = ($_POST['type'] ?? 'in') === 'out' ? 'out' : 'in';
$employee   = db_employee($employeeId);

$db = db_read();
if (!isset($db['attendance'][$employeeId])) {
    $db['attendance'][$employeeId] = ['today' => [], 'history' => []];
}
$today = &$db['attendance'][$employeeId]['today'];

$now = date('h:i A');

if ($type === 'out') {
    $today['clock_out']   = $now;
    $today['total_hours'] = clock_hours($today['clock_in'] ?? null, $now);
    // "onsite" specifically means clocked in with no clock-out yet (see
    // Mike Chen's seed record); once clocked out that's no longer true.
    if (($today['status'] ?? '') === 'onsite') {
        $today['status'] = 'present';
    }
} else {
    $today['clock_in']    = $now;
    $today['clock_out']   = null;
    $today['total_hours'] = null;
    $today['status']      = 'present';
}
unset($today);

db_write($db);

db_log_activity([
    'time_label'    => date('H:i'),
    'employee_name' => $employee['name'] ?? '',
    'event_type'    => $type === 'out' ? 'clock_out' : 'clock_in',
]);

header('Location: ../portal.php');
exit;
