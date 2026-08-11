<?php
// data.php — admin portal data, sourced from the backend API via
// frontend/lib/api.php. The backend reads from the hosted MySQL database.
require_once __DIR__ . '/../lib/api.php';

try {
    [$status, $body] = api_get('/admin/dashboard');
} catch (Throwable $e) {
    error_log('Dashboard API error: ' . $e->getMessage());
    $status = 0;
    $body = [];
}

$data = api_data($body);

// ---- 001_create_users ----
$employees = $data['employees'] ?? [];

$departmentOptions = ['All departments', 'Design', 'Engineering', 'Operations', 'Marketing'];

// ---- 003_create_attendance ----
$dashboardStats = $data['dashboard_stats'] ?? [
    'employees_onsite'   => 0,
    'checked_in_today'   => 0,
    'late_arrivals'      => 0,
    'employees_absent'   => 0,
    'on_time_rate_pct'   => 0,
];

$liveFeed = $data['live_feed'] ?? [];

$attendanceMonitoring = $data['attendance_monitoring'] ?? [];

// ---- 005_create_leave ----
$leaveTypeLabels = [
    'annual'    => 'Annual Leave',
    'sick'      => 'Sick Leave',
    'unpaid'    => 'Unpaid Leave',
    'emergency' => 'Emergency Leave',
    'other'     => 'Other Leave',
    'leave'     => 'Leave',
];

$pendingLeaveRequests = $data['pending_leave_requests'] ?? [];
$leaveHistory = $data['leave_history'] ?? [];

// ---- 006_create_settings ----
$systemSettings = [
    'company_name'           => 'TAPP Botanical Co.',
    'working_hours_start'    => '08:00',
    'working_hours_end'      => '17:00',
    'late_threshold_minutes' => 10,
];

$integrationSettings = [
    'qr_clock_in_enabled'       => true,
    'nfc_clock_in_enabled'      => true,
    'google_sheets_sync_enabled' => false,
];

$terminalStatus = [
    'device_name'   => 'tapp-pi-01',
    'status'        => 'connected',
    'network_label' => 'Local network',
];

$reportTiles = [
    ['key' => 'daily',           'title' => 'Daily Attendance',    'subtitle' => "Today's snapshot",                     'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/>', 'disabled' => false],
    ['key' => 'weekly',          'title' => 'Weekly Attendance',   'subtitle' => 'Needs historical data — coming soon',  'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 14h.01M12 14h.01M16 14h.01"/>', 'disabled' => true],
    ['key' => 'monthly',         'title' => 'Monthly Attendance',  'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M4 20V10M11 20V4M18 20v-7"/>', 'disabled' => true],
    ['key' => 'employee_hours',  'title' => 'Employee Hours',      'subtitle' => "Today's hours per person",             'icon' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>', 'disabled' => false],
    ['key' => 'leave',           'title' => 'Leave Reports',       'subtitle' => 'All requests & balances',          'icon' => '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>', 'disabled' => false],
    ['key' => 'custom',          'title' => 'Custom Range',        'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>', 'disabled' => true],
];

// The employee table has a single Status column that shows either today's
// attendance (when it's noteworthy — onsite/late) or, otherwise, the
// employee's employment state.
function employee_status_badge(array $emp): array
{
    $attendanceBadge = [
        'onsite' => ['class' => 'badge-onsite', 'label' => 'Onsite'],
        'late'   => ['class' => 'badge-late', 'label' => 'Late Today'],
    ];
    $employmentBadge = [
        'active'   => ['class' => 'badge-present', 'label' => 'Active'],
        'inactive' => ['class' => 'badge-absent', 'label' => 'Inactive'],
    ];
    return $attendanceBadge[$emp['today_attendance_status']] ?? $employmentBadge[$emp['status']];
}

function attendance_status_badge(string $status): array
{
    $badges = [
        'present' => ['class' => 'badge-present', 'label' => 'Present'],
        'onsite'  => ['class' => 'badge-onsite', 'label' => 'Currently Onsite'],
        'absent'  => ['class' => 'badge-absent', 'label' => 'Absent'],
        'late'    => ['class' => 'badge-late', 'label' => 'Late'],
    ];
    return $badges[$status] ?? ['class' => '', 'label' => ucfirst($status)];
}

function feed_dot_color(string $eventType): string
{
    $colors = [
        'clock_in'      => '#9aa574',
        'clock_out'     => '#d2a7a7',
        'leave_applied' => '#d3ac77',
        'leave_decided' => '#9aa574',
    ];
    return $colors[$eventType] ?? '#cdb9ab';
}

function feed_text(string $eventType): string
{
    $texts = [
        'clock_in'      => 'clocked in',
        'clock_out'     => 'clocked out',
        'leave_applied' => 'applied for leave',
        'leave_decided' => 'had a leave request decided',
    ];
    return $texts[$eventType] ?? $eventType;
}