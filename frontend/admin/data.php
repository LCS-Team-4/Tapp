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

// Fallback: load employees directly from the database when the HTTP API
// returns nothing (common with the PHP built-in server).
if ($employees === []) {
    try {
        $backendRoot = dirname(__DIR__, 2) . '/backend';
        $autoload = $backendRoot . '/vendor/autoload.php';
        $bootstrap = $backendRoot . '/bootstrap/app.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (!function_exists('config') && is_file($bootstrap)) {
                require $bootstrap;
            }
            $users = new \App\Repositories\UserRepository();
            $all = $users->findAll();
            $employees = [];
            foreach ($all as $u) {
                // Skip admin accounts — only show staff/employees
                if (($u['role'] ?? '') === 'admin') {
                    continue;
                }
                $name = $u['name'] ?? '';
                $parts = array_filter(array_map('trim', explode(' ', $name)));
                $initials = count($parts) === 0
                    ? '??'
                    : strtoupper(implode('', array_map(fn ($p) => $p[0], $parts)));
                $employees[] = [
                    'employee_id' => $u['employee_id'] ?? '',
                    'name' => $name,
                    'email' => $u['email'] ?? '',
                    'department' => $u['department'] ?? '',
                    'position' => $u['position'] ?? '',
                    'status' => 'active',
                    'today_attendance_status' => 'absent',
                    'today_is_late' => false,
                    'initials' => $initials,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('Admin employees DB fallback: ' . $e->getMessage());
    }
}

$employeeDepartmentOptions = ['Engineering', 'Design', 'Operations', 'Marketing', 'Finance', 'Security'];
$departmentOptions = array_merge(['All departments'], $employeeDepartmentOptions);

// ---- 003_create_attendance ----
$dashboardStats = $data['dashboard_stats'] ?? [
    'employees_onsite'   => 0,
    'present_count'      => 0,
    'checked_in_today'   => 0,
    'late_arrivals'      => 0,
    'employees_absent'   => 0,
    'on_time_count'      => 0,
    'on_time_rate_pct'   => 0,
];

$liveFeed = $data['live_feed'] ?? [];

$attendanceMonitoring = $data['attendance_monitoring'] ?? [];

// Employees who clocked in after the working-hours start + late threshold.
$lateArrivalsList = $data['late_arrivals_list'] ?? [];

// ---- 005_create_leave ----
$leaveTypeLabels = [
    'annual'    => 'Annual Leave',
    'sick'      => 'Sick Leave',
    'stu_leave' => 'Study Leave',
    'fr_leave'  => 'Family Responsibility Leave',
    'unpaid'    => 'Unpaid Leave',
    'emergency' => 'Emergency Leave',
    'other'     => 'Other Leave',
    'leave'     => 'Leave',
];

$pendingLeaveRequests = $data['pending_leave_requests'] ?? [];
$leaveHistory = $data['leave_history'] ?? [];

// ---- 006_create_settings ----
// Settings come from the backend API (the settings table). Fall back to
// sensible defaults if the API didn't return them.
$systemSettings = $data['system_settings'] ?? [
    'company_name'           => 'TAPP Botanical Co.',
    'working_hours_start'    => '08:00',
    'working_hours_end'      => '17:00',
    'late_threshold_minutes' => 10,
    'google_sheets_sync_enabled' => false,
    'google_sheets_webhook_url' => '',
];

$terminalStatus = array_merge([
    'device_name'   => 'tapp-pi-01',
    'status'        => 'disconnected',
    'network_label' => 'Local network',
], $data['terminal_status'] ?? []);

$reportTiles = [
    ['key' => 'daily',          'title' => 'Daily Attendance', 'subtitle' => 'View and export daily attendance records.', 'class' => 'report-green', 'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 14h.01M12 14h.01M16 14h.01"/>'],
    ['key' => 'weekly',          'title' => 'Weekly Attendance',   'subtitle' => 'Needs historical data — coming soon',  'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 14h.01M12 14h.01M16 14h.01"/>', 'disabled' => true],
    ['key' => 'monthly',         'title' => 'Monthly Attendance',  'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M4 20V10M11 20V4M18 20v-7"/>', 'disabled' => true],
    ['key' => 'leave',          'title' => 'Leave Reports',    'subtitle' => 'View and export employee leave records.',  'class' => 'report-rust',  'icon' => '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>'],
    ['key' => 'employee_hours', 'title' => 'Employee Hours',   'subtitle' => 'View and export employee working hours.',  'class' => 'report-green', 'icon' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>'],
    ['key' => 'custom',          'title' => 'Custom Range',        'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>', 'disabled' => true],
];

// The employee table has a single Status column that shows either today's
// attendance (when it's noteworthy — onsite/late) or, otherwise, the
// employee's employment state.
function employee_status_badge(array $emp): array
{
    $badges = [
        'onsite'  => ['class' => 'badge-onsite', 'label' => 'Onsite'],
        'present' => ['class' => 'badge-offsite', 'label' => 'Offsite'],
        'offsite' => ['class' => 'badge-offsite', 'label' => 'Offsite'],
        'absent'  => ['class' => 'badge-absent', 'label' => 'Absent'],
    ];
    return $badges[$emp['today_attendance_status'] ?? ''] ?? ['class' => 'badge-offsite', 'label' => 'Offsite'];
}

function attendance_status_badge(string $status, bool $isLate = false): array
{
    $badges = [
        'present' => ['class' => 'badge-offsite', 'label' => 'Offsite'],
        'offsite' => ['class' => 'badge-offsite', 'label' => 'Offsite'],
        'onsite'  => ['class' => 'badge-onsite', 'label' => 'Onsite'],
        'absent'  => ['class' => 'badge-absent', 'label' => 'Absent'],
    ];
    return $badges[$status] ?? ['class' => 'badge-offsite', 'label' => 'Offsite'];
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

// Extracts initials from a full name, e.g. "Sarah Lee" -> "SL".
function initials_of(string $name): string
{
    $parts = array_filter(array_map('trim', explode(' ', $name)));
    if (count($parts) === 0) {
        return '??';
    }
    return strtoupper(implode('', array_map(fn ($p) => $p[0], $parts)));
}
