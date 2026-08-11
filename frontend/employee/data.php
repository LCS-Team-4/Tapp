<?php
// data.php — employee portal data, sourced from the backend API via
// frontend/lib/api.php. The backend reads from the hosted MySQL database.
require_once __DIR__ . '/../lib/api.php';

$employeeId = $_SESSION['employee_id'] ?? '';

try {
    [$status, $body] = api_get('/users/profile');
} catch (Throwable $e) {
    error_log('Profile API error: ' . $e->getMessage());
    $status = 0;
    $body = [];
}

$data = api_data($body);

// ---- 001_create_users ----
$currentUser = $data['current_user'] ?? [
    'employee_id' => $employeeId,
    'name'        => $_SESSION['user_name'] ?? '',
    'email'       => '',
    'department'  => '',
    'position'    => '',
    'role'        => $_SESSION['role'] ?? 'employee',
    'role_label'  => $_SESSION['role_label'] ?? '',
    'initials'    => $_SESSION['initials'] ?? '??',
    'status'      => 'active',
];

// ---- 003_create_attendance ----
$todayStatus        = $data['today_status'] ?? [
    'status' => 'absent',
    'clock_in' => null,
    'clock_out' => null,
    'total_hours' => null,
    'week_hours_logged' => 0,
    'week_hours_target' => 40,
];
$attendanceHistory  = $data['attendance_history'] ?? [];

// ---- 005_create_leave ----
$leaveBalance = [
    'annual_leave_balance' => $data['leave_balance'] ?? 0,
];

$leaveRequests = $data['leave_requests'] ?? [];

// Value/label pairs so the leave form submits the real enum value
// (leave_requests.request_type) while still displaying a friendly label.
$leaveTypeOptions = [
    ['value' => 'annual',    'label' => 'Annual Leave'],
    ['value' => 'sick',      'label' => 'Sick Leave'],
    ['value' => 'unpaid',    'label' => 'Unpaid Leave'],
    ['value' => 'emergency', 'label' => 'Emergency Leave'],
    ['value' => 'other',     'label' => 'Other Leave'],
    ['value' => 'leave',     'label' => 'Leave'],
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