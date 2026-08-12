<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Services\AttendanceService;

class UsersController
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function getCurrentUser(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        return Response::json($user);
    }

    // Profile data for the employee portal: current user + attendance status
    // + history + leave balance + leave requests.
    public function profile(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $attendance = new AttendanceService();
        $todayStatus = $attendance->todayStatus($user['employee_id']);
        $history = $attendance->history($user['employee_id']);

        $leaveRepo = new \App\Repositories\LeaveRepository();
        $leaveRequests = $leaveRepo->listForUser((int) $user['id'], (string) $user['role']);

        return Response::json([
            'current_user' => $user,
            'today_status' => $todayStatus,
            'attendance_history' => $history,
            'leave_requests' => array_map([$this, 'shapeLeave'], $leaveRequests),
            'leave_balance' => $user['annual_leave_balance'] ?? 0,
        ]);
    }

    // Admin dashboard data: employees, attendance monitoring, live feed,
    // leave requests, counts.
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $attendance = new AttendanceService();
        $allUsers = $this->users->findAll();
        $attendanceRows = $attendance->todayAll();
        $feed = $attendance->feed();
        $leaveRepo = new \App\Repositories\LeaveRepository();
        $leaveRequests = $leaveRepo->listForUser(0, 'admin');
        $settingsRepo = new SettingsRepository();
        $settings = $settingsRepo->get();
        $lateArrivals = $attendance->lateArrivalsList();
        $lateCount = count($lateArrivals);

        // Employees: map to the shape admin/data.php expects
        $employees = [];
        foreach ($allUsers as $u) {
            if ($u['role'] !== 'admin') {
                $todayInfo = null;
                foreach ($attendanceRows as $row) {
                    if ($row['employee_id'] === $u['employee_id']) {
                        $todayInfo = $row;
                        break;
                    }
                }
                $employees[] = [
                    'employee_id' => $u['employee_id'],
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'department' => $u['department'],
                    'position' => $u['position'],
                    'status' => 'active',
                    'today_attendance_status' => $todayInfo['status'] ?? 'absent',
                    'today_is_late' => $todayInfo['is_late'] ?? false,
                    'initials' => $this->initials($u['name']),
                ];
            }
        }

        // Attendance monitoring: map with initials
        $attendanceMonitoring = array_map(function (array $row) {
            return [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'initials' => $this->initials($row['name']),
                'clock_in' => $row['clock_in'],
                'clock_out' => $row['clock_out'],
                'total_hours' => $row['total_hours'],
                'status' => $row['status'],
                'is_late' => $row['is_late'] ?? false,
            ];
        }, $attendanceRows);

        // Pending / history leave splits
        $pendingLeaveRequests = [];
        $leaveHistory = [];
        foreach ($leaveRequests as $req) {
            $row = [
                'leave_id' => $req['id'],
                'employee_name' => $req['name'] ?? '',
                'leave_type' => $req['leave_type'] ?? $req['request_type'] ?? '',
                'duration_days' => $this->durationDays($req['start_date'] ?? '', $req['end_date'] ?? ''),
                'reason' => $req['reason'],
                'status' => $req['status'] === 'rejected' ? 'declined' : $req['status'],
            ];
            if (($req['status'] ?? '') === 'pending') {
                $pendingLeaveRequests[] = $row;
            } else {
                $row['decided_at'] = $req['updated_at'] ?? null;
                $leaveHistory[] = $row;
            }
        }

        // Dashboard stats from attendance. Presence and lateness are kept
        // separate: 'status' reflects whether the employee is currently on
        // site (onsite = latest event is a clock-in; present = clocked in
        // today but has since clocked out), and 'is_late' describes the
        // clock-in time relative to working hours. So:
        //   - employees_onsite      = currently on site right now (onsite)
        //   - present_count         = clocked in earlier but already left
        //   - checked_in_today      = everyone who clocked in at all
        //                             (onsite + present)
        //   - employees_absent      = non-admin employees with no clock-in
        //   - on_time_count         = checked in and clocked in on time
        //   - late_arrivals         = checked in after the late cutoff
        $onsite = 0;
        $present = 0;
        $lateToday = 0;
        foreach ($attendanceRows as $row) {
            if ($row['status'] === 'onsite') $onsite++;
            if ($row['status'] === 'present') $present++;
            if ($row['is_late'] ?? false) $lateToday++;
        }

        $checkedInToday = $present + $onsite;
        $onTimeCount = max(0, $checkedInToday - $lateToday);
        $onSiteNow = $onsite;

        // Employees who never clocked in today are absent. Only non-admin
        // employees count toward the workforce.
        $nonAdminCount = 0;
        foreach ($allUsers as $u) {
            if ($u['role'] !== 'admin') {
                $nonAdminCount++;
            }
        }
        $absent = max(0, $nonAdminCount - $checkedInToday);

        $totalTracked = $checkedInToday > 0 ? $checkedInToday : 1;
        $onTimePct = round(($onTimeCount / $totalTracked) * 100);

        return Response::json([
            'employees' => $employees,
            'attendance_monitoring' => $attendanceMonitoring,
            'pending_leave_requests' => $pendingLeaveRequests,
            'leave_history' => $leaveHistory,
            'live_feed' => $feed,
            'late_arrivals_list' => $lateArrivals,
            'system_settings' => [
                'company_name' => $settings['company_name'] ?? 'TAPP Botanical Co.',
                'working_hours_start' => substr((string) ($settings['working_hours_start'] ?? '08:00:00'), 0, 5),
                'working_hours_end' => substr((string) ($settings['working_hours_end'] ?? '17:00:00'), 0, 5),
                'late_threshold_minutes' => (int) ($settings['late_threshold_minutes'] ?? 10),
            ],
            'dashboard_stats' => [
                'employees_onsite' => $onSiteNow,
                'present_count' => $present,
                'checked_in_today' => $checkedInToday,
                'late_arrivals' => $lateToday,
                'employees_absent' => $absent,
                'on_time_count' => $onTimeCount,
                'on_time_rate_pct' => $onTimePct,
            ],
        ]);
    }

    // All employees (admin employee tab)
    public function employees(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $allUsers = $this->users->findAll();
        $employees = array_map(function (array $u) {
            return [
                'employee_id' => $u['employee_id'],
                'name' => $u['name'],
                'initials' => $this->initials($u['name']),
                'email' => $u['email'],
                'department' => $u['department'],
                'position' => $u['position'],
                'status' => 'active',
            ];
        }, array_filter($allUsers, fn ($u) => $u['role'] !== 'admin'));

        return Response::json($employees);
    }

    // Register a new employee (admin action)
    public function register(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));

        if ($name === '' || $email === '') {
            return Response::error('Name and email are required', 400);
        }

        $nameParts = array_values(array_filter(array_map('trim', explode(' ', $name))));
        $firstName = $nameParts[0] ?? 'Unknown';
        $lastName = $nameParts[1] ?? '';

        // Generate a unique employee_id
        $base = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        $counter = 1;
        do {
            $employeeId = sprintf('EMP-%04d', $counter++);
        } while ($this->users->employeeIdExists($employeeId));

        $defaultPassword = bin2hex(random_bytes(5));

        try {
            $created = $this->users->create(
                $employeeId,
                $firstName,
                $lastName,
                $email,
                password_hash($defaultPassword, PASSWORD_BCRYPT),
                'staff',
                trim((string) $request->input('department', '')) ?: null,
                trim((string) $request->input('position', '')) ?: null,
            );
        } catch (\Throwable $e) {
            return Response::error('Unable to register employee: ' . $e->getMessage(), 500);
        }

        return Response::json([
            'employee_id' => $created->employeeId,
            'name' => $created->name,
            'email' => $created->email,
            'temporary_password' => $defaultPassword,
        ], 201);
    }

    

    public function update(Request $request, string $employeeId): Response
    {
        $user = $request->user();
        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $payload = $request->input();
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        if ($name === '' || $email === '') {
            return Response::error('Name and email are required', 400);
        }

        $existing = $this->users->findByEmployeeId($employeeId);
        if ($existing === null) {
            return Response::error('Employee not found', 404);
        }

        if ($email !== $existing->email && $this->users->emailExists($email)) {
            return Response::error('Email already in use', 409);
        }

        $updated = $this->users->updateByEmployeeId($employeeId, [
            'name' => $name,
            'email' => $email,
            'department' => $payload['department'] ?? null,
            'position' => $payload['position'] ?? null,
        ]);

        

        if ($updated === null) {
            return Response::error('Unable to update employee', 500);
        }

        return Response::json($updated->toArray());
    }

    public function delete(Request $request, string $employeeId): Response
    {
        $user = $request->user();
        if ($user === null || $user['role'] !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $existing = $this->users->findByEmployeeId($employeeId);
        if ($existing === null) {
            return Response::error('Employee not found', 404);
        }

        if ($existing->role === 'admin') {
            return Response::error('Admin accounts cannot be deleted from the employee roster', 400);
        }

        try {
            if (!$this->users->deleteByEmployeeId($employeeId)) {
                return Response::error('Employee not found', 404);
            }
        } catch (\PDOException) {
            return Response::error('Unable to delete employee because they have attendance or leave history', 409);
        }

        return Response::json(['deleted' => true, 'employee_id' => $employeeId]);
    }


   
    private function shapeLeave(array $req): array
    {
        $leaveType = $req['leave_type'] ?? $req['request_type'] ?? 'other';
        // Map 'rejected' -> 'declined' to match the frontend vocabulary
        $status = $req['status'] ?? 'pending';
        if ($status === 'rejected') {
            $status = 'declined';
        }

        return [
            'leave_id' => $req['id'],
            'employee_id' => $req['employee_id'] ?? '',
            'employee_name' => $req['name'] ?? '',
            'leave_type' => $leaveType,
            'start_date' => substr($req['start_date'] ?? '', 0, 10),
            'end_date' => substr($req['end_date'] ?? '', 0, 10),
            'duration_days' => $this->durationDays($req['start_date'] ?? '', $req['end_date'] ?? ''),
            'reason' => $req['reason'] ?? '',
            'status' => $status,
            'decided_at' => $req['updated_at'] ?? null,
        ];
    }

    private function durationDays(string $start, string $end): int
    {
        $s = new \DateTimeImmutable(substr($start, 0, 10));
        $e = new \DateTimeImmutable(substr($end, 0, 10));
        return $s->diff($e)->days + 1;
    }

    private function initials(string $name): string
    {
        $parts = array_filter(array_map('trim', explode(' ', $name)));
        if (count($parts) === 0) {
            return '??';
        }
        return strtoupper(implode('', array_map(fn ($p) => $p[0], $parts)));
    }
}
