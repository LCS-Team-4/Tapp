<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\AttendanceRepository;
use App\Repositories\UserRepository;

// The hosted schema stores one row per scan event (action 'in'/'out').
// The state machine is driven by users.status ('IN'/'OUT') — the single
// source of truth for whether an employee is currently clocked in. The
// attendance table is a pure event log; each toggle appends one 'in' or
// 'out' row and flips users.status atomically in the same transaction.
class AttendanceService
{
    // Minimum seconds between two clock events for the same employee.
    // Mirrors the Pi's cooldown intent (COOLDOWN_MINUTES) and prevents
    // double-click / rapid toggle spam from creating junk rows.
    public const COOLDOWN_SECONDS = 15;

    public function __construct(
        private readonly AttendanceRepository $attendanceRepository = new AttendanceRepository(),
        private readonly UserRepository $userRepository = new UserRepository(),
    ) {
    }

    // Toggle clock in/out for the given employee_id (web portal action).
    // Uses users.status as the source of truth, flips it atomically, and
    // appends the matching event row in the same transaction.
    public function toggle(string $employeeId, string $method = 'manual'): array
    {
        $user = $this->userRepository->findByEmployeeId($employeeId);
        if ($user === null) {
            return ['action' => 'unknown_employee'];
        }

        $this->assertNotOnCooldown($employeeId);

        // Atomic flip of users.status + append of the event row. The
        // conditional UPDATE makes the read-modify-write race-safe: if two
        // requests both see 'OUT', only the first UPDATE matches; the second
        // sees the already-flipped row and falls through to the other branch.
        $pdo = \App\Database\Connection::get();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET status = ?, updated_at = NOW() WHERE employee_id = ? AND status = ?'
            );
            $stmt->execute([
                $user->status === 'IN' ? 'OUT' : 'IN',
                $employeeId,
                $user->status,
            ]);

            $flipped = $stmt->rowCount() > 0;

            if ($flipped) {
                $action = $user->status === 'IN' ? 'out' : 'in';
                $this->attendanceRepository->recordEvent($employeeId, $action, $method);
            } else {
                // Another concurrent request already flipped the status.
                // Re-read to report the actual resulting state.
                $fresh = $this->userRepository->findByEmployeeId($employeeId);
                $action = $fresh !== null && $fresh->status === 'IN' ? 'in' : 'out';
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['action' => $action === 'in' ? 'clocked_in' : 'clocked_out'];
    }

    // Explicit clock-in — used by the Pi terminal's redeem path.
    public function clockIn(string $employeeId, string $method = 'device'): array
    {
        return $this->setStatus($employeeId, 'IN', $method);
    }

    // Explicit clock-out.
    public function clockOut(string $employeeId, string $method = 'device'): array
    {
        return $this->setStatus($employeeId, 'OUT', $method);
    }

    // Set an explicit clock state, idempotently: if the user is already in
    // the requested state, no event is appended (no duplicate rows).
    private function setStatus(string $employeeId, string $targetStatus, string $method): array
    {
        $user = $this->userRepository->findByEmployeeId($employeeId);
        if ($user === null) {
            return ['action' => 'unknown_employee'];
        }

        $this->assertNotOnCooldown($employeeId);

        $pdo = \App\Database\Connection::get();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET status = ?, updated_at = NOW() WHERE employee_id = ? AND status <> ?'
            );
            $stmt->execute([$targetStatus, $employeeId, $targetStatus]);

            if ($stmt->rowCount() > 0) {
                $action = $targetStatus === 'IN' ? 'in' : 'out';
                $this->attendanceRepository->recordEvent($employeeId, $action, $method);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['action' => $targetStatus === 'IN' ? 'clocked_in' : 'clocked_out'];
    }

    // Rejects a clock event if one was recorded too recently for this
    // employee (prevents double-tap / rapid spam).
    private function assertNotOnCooldown(string $employeeId): void
    {
        $latest = $this->attendanceRepository->findLatestEvent($employeeId);
        if ($latest === null) {
            return;
        }

        $lastTime = strtotime($latest['attendance_time']);
        if ($lastTime === false) {
            return;
        }

        if ((time() - $lastTime) < self::COOLDOWN_SECONDS) {
            throw new ValidationException('Please wait before scanning again');
        }
    }

    // Determine the current status for an employee today:
    // - 'onsite' if the latest event today was an 'in'
    // - 'present' if the earliest 'in' and latest 'out' both exist
    // - 'absent' if no events today
    public function todayStatus(string $employeeId): array
    {
        $events = $this->attendanceRepository->findToday($employeeId);

        if (count($events) === 0) {
            return [
                'status' => 'absent',
                'clock_in' => null,
                'clock_out' => null,
                'total_hours' => null,
                'week_hours_logged' => 0,
                'week_hours_target' => 40,
            ];
        }

        $first = $events[0];
        $last = $events[count($events) - 1];

        $clockIn = $first['action'] === 'in' ? $this->formatTime($first['attendance_time']) : null;
        $clockOut = null;
        $status = 'onsite';

        // Find the last 'out' event
        foreach (array_reverse($events) as $event) {
            if ($event['action'] === 'out') {
                $clockOut = $this->formatTime($event['attendance_time']);
                $status = 'present';
                break;
            }
        }

        $hours = $this->attendanceRepository->computeDailyHoursFromEvents($events, new \DateTimeImmutable('now'));

        return [
            'status' => $status,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'total_hours' => $hours,
            'week_hours_logged' => $this->weekHoursLogged($employeeId),
            'week_hours_target' => 40,
        ];
    }

    // Attendance history grouped by day for the employee history table.
    public function history(string $employeeId, int $limit = 30): array
    {
        $events = $this->attendanceRepository->findHistory($employeeId, $limit);

        // Group events by day
        $byDay = [];
        foreach ($events as $event) {
            $day = substr($event['attendance_time'], 0, 10);
            $byDay[$day][] = $event;
        }

        $history = [];
        foreach ($byDay as $day => $dayEvents) {
            $firstIn = null;
            $lastOut = null;
            foreach ($dayEvents as $event) {
                if ($event['action'] === 'in' && $firstIn === null) {
                    $firstIn = $event['attendance_time'];
                }
                if ($event['action'] === 'out') {
                    $lastOut = $event['attendance_time'];
                }
            }

            $dt = new \DateTimeImmutable($day);
            $history[] = [
                'date_label' => $dt->format('D, j M'),
                'clock_in' => $firstIn !== null ? substr($firstIn, 11, 5) : null,
                'clock_out' => $lastOut !== null ? substr($lastOut, 11, 5) : null,
                'total_hours' => $this->attendanceRepository->computeDailyHoursFromEvents($dayEvents),
                'status' => $this->dayStatus($firstIn, $lastOut),
            ];
        }

        return array_reverse($history);
    }

    private function weekHoursLogged(string $employeeId): float
    {
        $now = new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0, 0);
        $dayOfWeek = (int) $today->format('N');
        $weekStart = $dayOfWeek === 1
            ? $today
            : $today->modify('-' . ($dayOfWeek - 1) . ' days');

        $events = $this->attendanceRepository->findEventsBetween(
            $employeeId,
            $weekStart->format('Y-m-d'),
            $today->format('Y-m-d')
        );

        $byDay = [];
        foreach ($events as $event) {
            $day = substr($event['attendance_time'], 0, 10);
            $byDay[$day][] = $event;
        }

        $totalHours = 0.0;
        foreach ($byDay as $day => $dayEvents) {
            $fallbackEnd = $day === $today->format('Y-m-d') ? $now : null;
            $hours = $this->attendanceRepository->computeDailyHoursFromEvents($dayEvents, $fallbackEnd);
            if ($hours !== null) {
                $totalHours += $hours;
            }
        }

        return round($totalHours, 1);
    }

    // Feed of recent clock events for the admin dashboard.
    public function feed(int $limit = 20): array
    {
        return array_map(function (array $event): array {
            return [
                'time_label' => substr($event['attendance_time'] ?? '', 11, 5) ?: '',
                'employee_name' => $event['name'] ?? '',
                'event_type' => $event['action'] === 'in' ? 'clock_in' : 'clock_out',
            ];
        }, $this->attendanceRepository->findRecentFeed($limit));
    }

    // Today's attendance rows for the admin monitoring table.
    public function todayAll(): array
    {
        $events = $this->attendanceRepository->findTodayAll();

        // Group by employee_id
        $byEmployee = [];
        foreach ($events as $event) {
            $empId = $event['employee_id'];
            $byEmployee[$empId][] = $event;
        }

        $rows = [];
        foreach ($byEmployee as $empId => $empEvents) {
            $firstIn = null;
            $lastOut = null;
            foreach ($empEvents as $event) {
                if ($event['action'] === 'in' && $firstIn === null) {
                    $firstIn = $event['attendance_time'];
                }
                if ($event['action'] === 'out') {
                    $lastOut = $event['attendance_time'];
                }
            }

            $rows[] = [
                'employee_id' => $empId,
                'name' => $empEvents[0]['name'] ?? '',
                'clock_in' => $firstIn !== null ? substr($firstIn, 11, 5) : null,
                'clock_out' => $lastOut !== null ? substr($lastOut, 11, 5) : null,
                'total_hours' => null,
                'status' => $this->dayStatus($firstIn, $lastOut),
            ];
        }

        return $rows;
    }

    private function dayStatus(?string $clockIn, ?string $clockOut): string
    {
        if ($clockIn === null) {
            return 'absent';
        }
        if ($clockOut === null) {
            return 'onsite';
        }
        return 'present';
    }

    private function formatTime(string $datetime): string
    {
        $dt = new \DateTimeImmutable($datetime);
        return $dt->format('h:i A');
    }
}