<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\AttendanceRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserRepository;
use App\Services\GoogleSheetsService;

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
        private readonly SettingsRepository $settingsRepository = new SettingsRepository(),
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

        // Log the clock event to Google Sheets (if sync is enabled). This is
        // best-effort and never blocks or breaks the clock flow.
        try {
            $eventType = $action === 'in' ? 'clock_in' : 'clock_out';
            (new GoogleSheetsService())->logEvent($employeeId, $user->name, $eventType, $method);
        } catch (\Throwable $e) {
            error_log('GoogleSheetsService clock hook failed: ' . $e->getMessage());
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

        // Log the clock event to Google Sheets (if sync is enabled). This is
        // best-effort and never blocks or breaks the clock flow.
        try {
            $eventType = $targetStatus === 'IN' ? 'clock_in' : 'clock_out';
            (new GoogleSheetsService())->logEvent($employeeId, $user->name, $eventType, $method);
        } catch (\Throwable $e) {
            error_log('GoogleSheetsService clock hook failed: ' . $e->getMessage());
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
                // Still accumulate the week's hours even when the employee
                // hasn't clocked in yet today (e.g. early morning or a day
                // off) so the dashboard "This Week" card is never stale.
                'week_hours_logged' => $this->weekHoursLogged($employeeId),
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

    // Feed of recent clock events for the admin dashboard. Entries are
    // newest-first (the repository no longer reverses the DESC query), so
    // the live feed shows the latest activity at the top.
    public function feed(int $limit = 20): array
    {
        return array_map(function (array $event): array {
            $date = substr($event['attendance_time'] ?? '', 0, 10);
            $dt = $date !== '' ? new \DateTimeImmutable($date) : null;
            return [
                'time_label' => substr($event['attendance_time'] ?? '', 11, 5) ?: '',
                'date_label' => $dt !== null ? $dt->format('D, j M') : '',
                'employee_name' => $event['name'] ?? '',
                'event_type' => $event['action'] === 'in' ? 'clock_in' : 'clock_out',
            ];
        }, $this->attendanceRepository->findRecentFeed($limit));
    }

    // Today's attendance rows for the admin monitoring table. Each row's
    // status is 'late' when the employee's first clock-in today was after
    // the configured working-hours start + late threshold (from settings).
    public function todayAll(): array
    {
        $events = $this->attendanceRepository->findTodayAll();

        // Group by employee_id
        $byEmployee = [];
        foreach ($events as $event) {
            $empId = $event['employee_id'];
            $byEmployee[$empId][] = $event;
        }

        $settings = $this->settingsRepository->get();
        $lateCutoff = $this->lateCutoffTime($settings);

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

            // The status reflects whether the employee is currently on site:
            // when the latest event today is an 'in' they are still onsite,
            // when it's an 'out' they have left for the day and count as
            // 'present'. 'absent' only when they never clocked in at all.
            $status = $this->dayStatusFromEvents($empEvents);
            $isLate = false;
            if ($status !== 'absent' && $firstIn !== null && $lateCutoff !== null) {
                $clockInTime = substr($firstIn, 11, 8); // HH:MM:SS
                if ($clockInTime > $lateCutoff) {
                    $isLate = true;
                }
            }

            $hours = $this->attendanceRepository->computeDailyHoursFromEvents($empEvents, new \DateTimeImmutable('now'));

            $rows[] = [
                'employee_id' => $empId,
                'name' => $empEvents[0]['name'] ?? '',
                'clock_in' => $firstIn !== null ? substr($firstIn, 11, 5) : null,
                'clock_out' => $lastOut !== null ? substr($lastOut, 11, 5) : null,
                'total_hours' => $hours,
                'status' => $status,
                'is_late' => $isLate,
            ];
        }

        return $rows;
    }

    // Builds attendance report rows for a date range (inclusive), one row
    // per employee per day with a clock event. Used by the admin Reports
    // export so weekly/monthly reports reflect the full range, not just
    // today's data.
    public function reportBetween(string $startDate, string $endDate): array
    {
        $events = $this->attendanceRepository->findBetweenDates($startDate, $endDate);

        // Group events by employee_id then by day.
        $byEmployeeDay = [];
        foreach ($events as $event) {
            $empId = $event['employee_id'];
            $day = substr($event['attendance_time'], 0, 10);
            $byEmployeeDay[$empId][$day][] = $event;
        }

        $settings = $this->settingsRepository->get();
        $lateCutoff = $this->lateCutoffTime($settings);

        $rows = [];
        foreach ($byEmployeeDay as $empId => $days) {
            foreach ($days as $day => $dayEvents) {
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

                $status = $this->dayStatusFromEvents($dayEvents);
                $isLate = false;
                if ($status !== 'absent' && $firstIn !== null && $lateCutoff !== null) {
                    $clockInTime = substr($firstIn, 11, 8); // HH:MM:SS
                    if ($clockInTime > $lateCutoff) {
                        $isLate = true;
                    }
                }

                $hours = $this->attendanceRepository->computeDailyHoursFromEvents($dayEvents);

                $rows[] = [
                    'date' => $day,
                    'employee_id' => $empId,
                    'name' => $dayEvents[0]['name'] ?? '',
                    'clock_in' => $firstIn !== null ? substr($firstIn, 11, 5) : null,
                    'clock_out' => $lastOut !== null ? substr($lastOut, 11, 5) : null,
                    'total_hours' => $hours,
                    'status' => $status,
                    'is_late' => $isLate,
                ];
            }
        }

        // Sort by date, then by employee name.
        usort($rows, function (array $a, array $b): int {
            return [$a['date'], $a['name']] <=> [$b['date'], $b['name']];
        });

        return $rows;
    }

    // Counts how many employees clocked in late today, based on the
    // configured working-hours start + late threshold from settings.
    public function lateArrivalsCount(): int
    {
        $rows = $this->todayAll();
        $count = 0;
        foreach ($rows as $row) {
            if ($row['is_late']) {
                $count++;
            }
        }
        return $count;
    }

    // Returns the list of employees who clocked in late today, with their
    // clock-in time and how late they were (in minutes).
    public function lateArrivalsList(): array
    {
        $rows = $this->todayAll();
        $settings = $this->settingsRepository->get();
        $lateCutoff = $this->lateCutoffTime($settings);

        $late = [];
        foreach ($rows as $row) {
            if (!$row['is_late'] || $row['clock_in'] === null || $lateCutoff === null) {
                continue;
            }

            $clockIn = $row['clock_in']; // HH:MM
            $cutoff = substr($lateCutoff, 0, 5); // HH:MM

            $clockInMinutes = $this->timeToMinutes($clockIn);
            $cutoffMinutes = $this->timeToMinutes($cutoff);
            $minutesLate = max(0, $clockInMinutes - $cutoffMinutes);

            $late[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'clock_in' => $clockIn,
                'minutes_late' => $minutesLate,
            ];
        }

        // Sort by most late first
        usort($late, fn ($a, $b) => $b['minutes_late'] <=> $a['minutes_late']);

        return $late;
    }

    // Computes the cutoff time (HH:MM:SS) after which a clock-in is
    // considered late: working_hours_start + late_threshold_minutes.
    private function lateCutoffTime(array $settings): ?string
    {
        $start = $settings['working_hours_start'] ?? null;
        if ($start === null || $start === '') {
            return null;
        }

        $threshold = (int) ($settings['late_threshold_minutes'] ?? 10);

        // Parse HH:MM or HH:MM:SS
        $parts = explode(':', (string) $start);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);

        $totalMinutes = $hours * 60 + $minutes + $threshold;
        $newHours = intdiv($totalMinutes, 60) % 24;
        $newMinutes = $totalMinutes % 60;

        return sprintf('%02d:%02d:%02d', $newHours, $newMinutes, $seconds);
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int) ($parts[0] ?? 0) * 60 + (int) ($parts[1] ?? 0);
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

    // Determines the current presence status from today's event history:
    // - 'absent' if there was never a clock-in today
    // - 'onsite' if the latest event is a clock-in (currently on site)
    // - 'present' if the latest event is a clock-out (left for the day)
    private function dayStatusFromEvents(array $events): string
    {
        $hasClockIn = false;
        foreach ($events as $event) {
            if ($event['action'] === 'in') {
                $hasClockIn = true;
                break;
            }
        }
        if (!$hasClockIn) {
            return 'absent';
        }

        $latest = $events[count($events) - 1];
        return $latest['action'] === 'out' ? 'present' : 'onsite';
    }

    private function formatTime(string $datetime): string
    {
        $dt = new \DateTimeImmutable($datetime);
        return $dt->format('h:i A');
    }
}