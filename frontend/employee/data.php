<?php
// data.php — employee portal data, sourced from frontend/data/db.json via
// frontend/lib/db.php, keyed by the logged-in employee ($_SESSION set in
// login_process.php). Field names/shapes match what portal.php and
// employee.js already expect — only where the data comes from changed.
require_once __DIR__ . '/../lib/db.php';

$employeeId = $_SESSION['employee_id'] ?? '';
$employee   = db_employee($employeeId) ?? [];
$db         = db_read();

// ---- 001_create_users ----
$currentUser = [
    'employee_id' => $employee['id']          ?? '',
    'name'        => $employee['name']        ?? '',
    'email'       => $employee['email']       ?? '',
    'department'  => $employee['department']  ?? '',
    'position'    => $employee['position']    ?? '',
    'role'        => $employee['role']        ?? 'employee',
    'role_label'  => ($employee['department'] ?? '') . ' · ' . ($employee['id'] ?? ''),
    'initials'    => $employee['initials']    ?? '??',
    'status'      => 'active', // users.status enum: active | inactive — no inactive seed users yet
];

// ---- 003_create_attendance ----
$attendanceRecord  = $db['attendance'][$employeeId] ?? ['today' => [], 'history' => []];
$todayStatus        = $attendanceRecord['today'];
$attendanceHistory   = $attendanceRecord['history'];

// ---- 005_create_leave ----
// annual_leave_balance actually lives on `users`, not leave_requests — kept
// as its own array here since the employee portal has no other use for a
// full $currentUser-shaped record on this screen.
$leaveBalance = [
    'annual_leave_balance' => $employee['annual_leave_balance'] ?? 0,
];

// leave_requests.leave_type enum: annual | sick | unpaid | emergency
$leaveRequests = array_values(array_filter($db['leave_requests'] ?? [], function ($req) use ($employeeId) {
    return $req['employee_id'] === $employeeId;
}));

// Value/label pairs so the leave form submits the real enum value
// (leave_requests.leave_type) while still displaying a friendly label.
$leaveTypeOptions = [
    ['value' => 'annual',    'label' => 'Annual Leave'],
    ['value' => 'sick',      'label' => 'Sick Leave'],
    ['value' => 'unpaid',    'label' => 'Unpaid Leave'],
    ['value' => 'emergency', 'label' => 'Emergency Leave'],
];

function leave_type_label(string $value, array $leaveTypeOptions): string
{
    foreach ($leaveTypeOptions as $opt) {
        if ($opt['value'] === $value) {
            return $opt['label'];
        }
    }
    return $value;
}

// Renders leave_requests.start_date/end_date (ISO dates) as the mockup's
// "15 – 17 Jul" / single-day "2 Jun" display string.
function format_date_range(string $startDate, string $endDate): string
{
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    [$sy, $sm, $sd] = array_map('intval', explode('-', $startDate));
    [$ey, $em, $ed] = array_map('intval', explode('-', $endDate));

    if ($startDate === $endDate) {
        return "{$sd} {$months[$sm - 1]}";
    }
    if ($sy === $ey && $sm === $em) {
        return "{$sd} – {$ed} {$months[$sm - 1]}";
    }
    return "{$sd} {$months[$sm - 1]} – {$ed} {$months[$em - 1]}";
}
