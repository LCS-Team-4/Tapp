<?php
// data.php — placeholder data for the admin portal, mirrored from
// ../../../Attendance-tracking-system/frontend/admin/src/data/placeholder.js.
// There is no backend to fetch from yet (backend/app/** are empty stubs);
// field names/enum values match the schema drafted on feature/Database-schema
// (see database/schema.sql in Attendance-tracking-system) so this can be
// swapped for real queries later without reshaping the views that use it.
//
// This mirrors the whole source file (including leave/settings/reports data
// not yet rendered by any panel) so later chunks can build a-leave,
// a-reports, and a-settings on top of it without reworking this file.

// ---- 001_create_users ----
// employment state (users.status: active | inactive) and today's clock
// status (attendance.status: present | late | absent | onsite) are two
// different concepts that happen to share badge styling in the Employees
// tab — modeled as two separate fields per employee, per the real schema.
// TODO: replace with a real lookup once
// backend/app/Http/Controllers/EmployeeController.php exists.
$employees = [
    ['employee_id' => 'EMP-0101', 'name' => 'John Smith', 'initials' => 'JS', 'email' => 'john.smith@tapp.co', 'department' => 'Engineering', 'position' => 'Backend Dev',     'status' => 'active', 'today_attendance_status' => 'present'],
    ['employee_id' => 'EMP-0142', 'name' => 'Sarah Lee',  'initials' => 'SL', 'email' => 'sarah.lee@tapp.co',  'department' => 'Design',      'position' => 'UI Designer',     'status' => 'active', 'today_attendance_status' => 'present'],
    ['employee_id' => 'EMP-0118', 'name' => 'Mike Chen',  'initials' => 'MC', 'email' => 'mike.chen@tapp.co',  'department' => 'Operations',  'position' => 'Site Supervisor', 'status' => 'active', 'today_attendance_status' => 'onsite'],
    ['employee_id' => 'EMP-0155', 'name' => 'Jane Park',  'initials' => 'JP', 'email' => 'jane.park@tapp.co',  'department' => 'Marketing',   'position' => 'Content Lead',    'status' => 'active', 'today_attendance_status' => 'present'],
    ['employee_id' => 'EMP-0163', 'name' => 'Tom Reyes',  'initials' => 'TR', 'email' => 'tom.reyes@tapp.co',  'department' => 'Engineering', 'position' => 'QA Engineer',     'status' => 'active', 'today_attendance_status' => 'late'],
];

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

// Static UI flavor for now — not backed by a live feed table/endpoint yet.
// TODO: replace with a real lookup once
// backend/app/Http/Controllers/FeedController.php exists.
$liveFeed = [
    ['time_label' => '08:01', 'employee_name' => 'John Smith', 'event_type' => 'clock_in'],
    ['time_label' => '08:03', 'employee_name' => 'Sarah Lee',  'event_type' => 'clock_in'],
    ['time_label' => '08:15', 'employee_name' => 'Mike Chen',  'event_type' => 'clock_out'],
    ['time_label' => '08:20', 'employee_name' => 'Jane Park',  'event_type' => 'leave_applied'],
    ['time_label' => '08:31', 'employee_name' => 'Tom Reyes',  'event_type' => 'marked_late'],
];

$attendanceMonitoring = [
    ['employee_id' => 'EMP-0101', 'name' => 'John Smith', 'initials' => 'JS', 'clock_in' => '08:01', 'clock_out' => '17:00', 'total_hours' => 8.0,  'status' => 'present'],
    ['employee_id' => 'EMP-0142', 'name' => 'Sarah Lee',  'initials' => 'SL', 'clock_in' => '08:15', 'clock_out' => null,    'total_hours' => null, 'status' => 'onsite'],
    ['employee_id' => 'EMP-0118', 'name' => 'Mike Chen',  'initials' => 'MC', 'clock_in' => '07:59', 'clock_out' => '17:10', 'total_hours' => 8.2,  'status' => 'present'],
    ['employee_id' => 'EMP-0155', 'name' => 'Jane Park',  'initials' => 'JP', 'clock_in' => null,    'clock_out' => null,    'total_hours' => null, 'status' => 'absent'],
    ['employee_id' => 'EMP-0163', 'name' => 'Tom Reyes',  'initials' => 'TR', 'clock_in' => '08:31', 'clock_out' => '17:05', 'total_hours' => 7.6,  'status' => 'late'],
];

// ---- 005_create_leave ----
// leave_requests.leave_type enum: annual | sick | unpaid | emergency
$leaveTypeLabels = [
    'annual'    => 'Annual Leave',
    'sick'      => 'Sick Leave',
    'unpaid'    => 'Unpaid Leave',
    'emergency' => 'Emergency Leave',
];

// TODO: replace with a real lookup once
// backend/app/Http/Controllers/LeaveController.php exists. Not rendered
// until a-leave ships in a later chunk.
$pendingLeaveRequests = [
    ['leave_id' => 1, 'employee_name' => 'Jane Park',  'leave_type' => 'annual', 'duration_days' => 3, 'reason' => 'Family emergency', 'status' => 'pending'],
    ['leave_id' => 2, 'employee_name' => 'Tom Reyes',  'leave_type' => 'sick',   'duration_days' => 1, 'reason' => 'Flu symptoms',      'status' => 'pending'],
    ['leave_id' => 3, 'employee_name' => 'John Smith', 'leave_type' => 'unpaid', 'duration_days' => 2, 'reason' => 'Personal matters',  'status' => 'pending'],
];

// Already-decided leave requests, kept separate from $pendingLeaveRequests so
// the decision isn't lost once Approve/Decline is clicked.
$leaveHistory = [
    ['leave_id' => 4, 'employee_name' => 'Sarah Lee', 'leave_type' => 'annual', 'duration_days' => 5, 'reason' => 'Family vacation', 'status' => 'approved', 'decided_at' => '2026-07-20'],
    ['leave_id' => 5, 'employee_name' => 'Mike Chen', 'leave_type' => 'sick',   'duration_days' => 2, 'reason' => 'Migraine',        'status' => 'declined', 'decided_at' => '2026-07-22'],
];

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
        'marked_late'   => '#c26a52',
    ];
    return $colors[$eventType] ?? '#cdb9ab';
}

function feed_text(string $eventType): string
{
    $texts = [
        'clock_in'      => 'clocked in',
        'clock_out'     => 'clocked out',
        'leave_applied' => 'applied for leave',
        'marked_late'   => 'marked late',
    ];
    return $texts[$eventType] ?? $eventType;
}
