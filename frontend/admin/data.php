<?php
// data.php — admin portal data. $employees/$attendanceMonitoring/leave
// data/$liveFeed are sourced from frontend/data/db.json via
// frontend/lib/db.php — the same underlying records employee/data.php reads
// for each employee's own view, so numbers never diverge between what an
// employee sees for themselves and what admin sees for them.
// $dashboardStats/$departmentOptions/$systemSettings/$integrationSettings/
// $terminalStatus/$reportTiles stay static placeholder data (out of scope
// for this pass — they aren't per-employee records db.json models).
require_once __DIR__ . '/../lib/db.php';

$db = db_read();

// ---- 001_create_users ----
// employment state (users.status: active | inactive) and today's clock
// status (attendance.status: present | late | absent | onsite) are two
// different concepts that happen to share badge styling in the Employees
// tab — modeled as two separate fields per employee, per the real schema.
$employees = [];
foreach ($db['users'] ?? [] as $user) {
    if ($user['role'] !== 'employee') {
        continue;
    }
    $employees[] = [
        'employee_id'             => $user['id'],
        'name'                    => $user['name'],
        'initials'                => $user['initials'],
        'email'                   => $user['email'],
        'department'              => $user['department'],
        'position'                => $user['position'],
        'status'                  => 'active', // no inactive seed users yet
        'today_attendance_status' => $db['attendance'][$user['id']]['today']['status'] ?? 'absent',
    ];
}

$departmentOptions = ['All departments', 'Design', 'Engineering', 'Operations', 'Marketing'];

// ---- 003_create_attendance ----
// TODO: replace with a real lookup once
// backend/app/Services/AttendanceService.php exists.
$dashboardStats = [
    'employees_onsite'   => 38,
    'checked_in_today'   => 44,
    'late_arrivals'      => 5,
    'employees_absent'   => 3,
    'on_time_rate_pct'   => 86,
];

// Most recent entries from activity_log, same shape the dashboard already
// expects (time_label/employee_name/event_type) — activity_log is the
// authoritative event source now, not a hand-maintained flavor list.
$liveFeed = $db['activity_log'] ?? [];

// Same today-attendance records employee/data.php reads for each
// employee's own $todayStatus — so e.g. Sarah's clock-in here is always
// identical to what Sarah sees on her own dashboard, never a second,
// independently-maintained copy of the same fact.
$attendanceMonitoring = [];
foreach ($employees as $emp) {
    $today = $db['attendance'][$emp['employee_id']]['today'] ?? [];
    $attendanceMonitoring[] = [
        'employee_id' => $emp['employee_id'],
        'name'        => $emp['name'],
        'initials'    => $emp['initials'],
        'clock_in'    => $today['clock_in']  ?? null,
        'clock_out'   => $today['clock_out'] ?? null,
        'total_hours' => $today['total_hours'] ?? null,
        'status'      => $today['status'] ?? 'absent',
    ];
}

// ---- 005_create_leave ----
// leave_requests.leave_type enum: annual | sick | unpaid | emergency
$leaveTypeLabels = [
    'annual'    => 'Annual Leave',
    'sick'      => 'Sick Leave',
    'unpaid'    => 'Unpaid Leave',
    'emergency' => 'Emergency Leave',
];

// Same leave_requests db.json rows employee/data.php filters to the current
// employee — here split by status instead of by employee_id, so a request
// Sarah sees as "Pending" on her own Leave sub-tab is the exact same record
// admin sees under Pending Requests, not a separately-seeded duplicate.
$pendingLeaveRequests = [];
$leaveHistory = [];
foreach ($db['leave_requests'] ?? [] as $req) {
    $row = [
        'leave_id'       => $req['leave_id'],
        'employee_name'  => $req['employee_name'],
        'leave_type'     => $req['leave_type'],
        'duration_days'  => $req['duration_days'],
        'reason'         => $req['reason'],
        'status'         => $req['status'],
    ];
    if ($req['status'] === 'pending') {
        $pendingLeaveRequests[] = $row;
    } else {
        $row['decided_at'] = $req['decided_at'];
        $leaveHistory[] = $row;
    }
}

// ---- 006_create_settings ----
// Not rendered until a-settings ships in a later chunk.
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

// Raspberry Pi terminal status shown in Settings > Integrations — really
// belongs to a terminals table (002_create_terminals.sql) once that's built.
$terminalStatus = [
    'device_name'   => 'tapp-pi-01',
    'status'        => 'connected',
    'network_label' => 'Local network',
];

// Report tile definitions for the Reports tab (not rendered until a-reports
// ships in a later chunk). `disabled` marks tiles with no backing data.
$reportTiles = [
    ['key' => 'daily',           'title' => 'Daily Attendance',    'subtitle' => "Today's snapshot",                     'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/>', 'disabled' => false],
    ['key' => 'weekly',          'title' => 'Weekly Attendance',   'subtitle' => 'Needs historical data — coming soon',  'icon' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M8 14h.01M12 14h.01M16 14h.01"/>', 'disabled' => true],
    ['key' => 'monthly',         'title' => 'Monthly Attendance',  'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M4 20V10M11 20V4M18 20v-7"/>', 'disabled' => true],
    ['key' => 'employee_hours',  'title' => 'Employee Hours',      'subtitle' => "Today's hours per person",             'icon' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>', 'disabled' => false],
    ['key' => 'leave',           'title' => 'Leave Reports',       'subtitle' => 'All requests &amp; balances',          'icon' => '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>', 'disabled' => false],
    ['key' => 'custom',          'title' => 'Custom Range',        'subtitle' => 'Needs historical data — coming soon',  'icon' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>', 'disabled' => true],
];

// The employee table has a single Status column that shows either today's
// attendance (when it's noteworthy — onsite/late) or, otherwise, the
// employee's employment state — mirrors employees.js's ATTENDANCE_BADGE /
// EMPLOYMENT_BADGE lookup-with-fallback.
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
