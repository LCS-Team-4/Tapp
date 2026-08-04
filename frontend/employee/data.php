<?php
// data.php — placeholder data for the employee portal, mirrored from
// ../../../Attendance-tracking-system/frontend/employee/src/data/placeholder.js.
// There is no backend to fetch from yet (backend/app/** are empty stubs);
// field names/enum values match the schema drafted on feature/Database-schema
// (see database/schema.sql in Attendance-tracking-system) so this can be
// swapped for real queries later without reshaping the views that use it.

// ---- 001_create_users ----
// TODO: replace with a real lookup once
// backend/app/Http/Controllers/AuthController.php exists.
$currentUser = [
    'employee_id' => 'EMP-0142',
    'name'        => 'Sarah Lee',
    'email'       => 'sarah.lee@tapp.co',
    'department'  => 'Design',
    'position'    => 'UI Designer',
    'role'        => 'employee',
    'role_label'  => 'EMP-0142',
    'initials'    => 'SL',
    'status'      => 'active', // users.status enum: active | inactive
];

// ---- 003_create_attendance ----
// TODO: replace with a real lookup once
// backend/app/Services/AttendanceService.php exists.
$todayStatus = [
    'clock_in'  => '08:03 AM',
    'clock_out' => null,
    'status'    => 'present', // attendance.status enum: present | late | absent | onsite

    // No migration yet — future scope, not backed by a real column.
    'week_hours_logged' => 32.5,
    'week_hours_target'  => 40,
];

$attendanceHistory = [
    ['date_label' => 'Mon, 27 Jul', 'clock_in' => '08:01', 'clock_out' => '17:00', 'total_hours' => 8.0, 'status' => 'present'],
    ['date_label' => 'Tue, 28 Jul', 'clock_in' => '08:12', 'clock_out' => '17:05', 'total_hours' => 8.1, 'status' => 'late'],
    ['date_label' => 'Wed, 29 Jul', 'clock_in' => null,    'clock_out' => null,    'total_hours' => null, 'status' => 'absent'],
    ['date_label' => 'Thu, 30 Jul', 'clock_in' => '07:58', 'clock_out' => '17:02', 'total_hours' => 8.1, 'status' => 'present'],
    ['date_label' => 'Fri, 31 Jul', 'clock_in' => '08:20', 'clock_out' => '16:50', 'total_hours' => 7.5, 'status' => 'late'],
];

// ---- 005_create_leave ----
// TODO: replace with a real lookup once
// backend/app/Http/Controllers/LeaveController.php exists.

// annual_leave_balance actually lives on `users`, not leave_requests — kept
// as its own array here since the employee portal has no other use for a
// full $currentUser-shaped record on this screen.
$leaveBalance = [
    'annual_leave_balance' => 12,
];

// leave_requests.leave_type enum: annual | sick | unpaid | emergency
$leaveRequests = [
    ['leave_id' => 1, 'leave_type' => 'annual', 'start_date' => '2026-07-15', 'end_date' => '2026-07-17', 'duration_days' => 3, 'reason' => 'Family Event', 'status' => 'pending'],
    ['leave_id' => 2, 'leave_type' => 'sick',    'start_date' => '2026-06-02', 'end_date' => '2026-06-02', 'duration_days' => 1, 'reason' => 'Flu',           'status' => 'approved'],
    ['leave_id' => 3, 'leave_type' => 'unpaid',  'start_date' => '2025-03-14', 'end_date' => '2025-03-14', 'duration_days' => 1, 'reason' => 'Personal',      'status' => 'declined'],
];

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
