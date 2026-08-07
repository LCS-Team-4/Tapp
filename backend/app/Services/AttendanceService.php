<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\AttendanceRepository;

// The hosted schema stores one row per scan event (action 'in'/'out').
// The state machine: clock in creates an 'in' event, clock out creates an
// 'out' event. The latest event determines current state.
class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository = new AttendanceRepository(),
    ) {
    }

    // Toggle clock in/out for the given employee_id (web portal action).
    public function toggle(string $employeeId, string $method = 'manual'): array
    {
        $latest = $this->attendanceRepository->findLatestEvent($employeeId);

        if ($latest === null || $latest['action'] === 'out') {
            $this->attendanceRepository->recordEvent($employeeId, 'in', $method);
            return ['action' => 'clocked_in'];
        }

        $this->attendanceRepository->recordEvent($employeeId, 'out', $method);
        return ['action' => 'clocked_out'];
    }

    // Explicit clock-in — used by the Pi terminal's redeem path.
    public function clockIn(string $employeeId, string $method = 'device'): array
    {
        $this->attendanceRepository->recordEvent($employeeId, 'in', $method);
        return ['action' => 'clocked_in'];
    }

    // Explicit clock-out.
    public function clockOut(string $employeeId, string $method = 'device'): array
    {
        $this->attendanceRepository->recordEvent($employeeId, 'out', $method);
        return ['action' => 'clocked_out'];
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

        $hours = null;
        if ($clockIn !== null && $clockOut !== null) {
            $hours = $this->attendanceRepository->computeDailyHours($employeeId, date('Y-m-d'));
        }

        return [
            'status' => $status,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'total_hours' => $hours,
            'week_hours_logged' => 0,
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
                'total_hours' => $this->attendanceRepository->computeDailyHours($employeeId, $day),
                'status' => $this->dayStatus($firstIn, $lastOut),
            ];
        }

        return array_reverse($history);
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