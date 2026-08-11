<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
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

        // Dashboard stats from attendance
        $onsite = 0;
        $present = 0;
        $absent = 0;
        foreach ($attendanceRows as $row) {
            if ($row['status'] === 'onsite') $onsite++;
            if ($row['status'] === 'present') $present++;
            if ($row['status'] === 'absent') $absent++;
        }

        $totalOnTime = $present + $onsite;
        $totalTracked = count($attendanceRows) > 0 ? count($attendanceRows) : 1;
        $onTimePct = round(($totalOnTime / $totalTracked) * 100);

        return Response::json([
            'employees' => $employees,
            'attendance_monitoring' => $attendanceMonitoring,
            'pending_leave_requests' => $pendingLeaveRequests,
            'leave_history' => $leaveHistory,
            'live_feed' => $feed,
            'dashboard_stats' => [
                'employees_onsite' => $onsite,
                'checked_in_today' => $present + $onsite,
                'late_arrivals' => 0,
                'employees_absent' => $absent,
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