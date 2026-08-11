<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

// Attendance repository adapted for the hosted schema: one row per scan
// event (action 'in' or 'out'), indexed by employee_id string, with an
// attendance_time timestamp.
class AttendanceRepository
{
    // Records a clock-in or clock-out event in the hosted schema's action-log.
    public function recordEvent(string $employeeId, string $action, string $method = 'manual'): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO attendance (employee_id, action, attendance_time, check_in_method, sync_status, location, device_info) '
            . "VALUES (?, ?, NOW(), ?, 'synced', 'Main Entrance', 'Web Portal')"
        );
        $stmt->execute([$employeeId, $action, $method]);

        return (int) Connection::get()->lastInsertId();
    }

    // Returns the most recent clock event for a given employee.
    public function findLatestEvent(string $employeeId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, employee_id, action, attendance_time, check_in_method '
            . 'FROM attendance WHERE employee_id = ? ORDER BY attendance_time DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // Returns today's events for one employee.
    public function findToday(string $employeeId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, employee_id, action, attendance_time, check_in_method '
            . "FROM attendance WHERE employee_id = ? AND DATE(attendance_time) = CURDATE() ORDER BY attendance_time ASC, id ASC"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Returns today's events for all employees, joined with user names.
    public function findTodayAll(): array
    {
        $stmt = Connection::get()->query(
            'SELECT a.id, a.employee_id, a.action, a.attendance_time, a.check_in_method, '
            . "CONCAT_WS(' ', u.first_name, u.last_name) AS name "
            . 'FROM attendance a '
            . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
            . 'WHERE DATE(a.attendance_time) = CURDATE() '
            . 'ORDER BY a.attendance_time ASC, a.id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Returns recent attendance history for one employee.
    public function findHistory(string $employeeId, int $limit = 30): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, employee_id, action, attendance_time, check_in_method '
            . 'FROM attendance WHERE employee_id = ? ORDER BY attendance_time DESC, id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $employeeId, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findEventsBetween(string $employeeId, string $startDate, string $endDate): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT employee_id, action, attendance_time '
            . 'FROM attendance '
            . 'WHERE employee_id = ? AND DATE(attendance_time) BETWEEN ? AND ? '
            . 'ORDER BY attendance_time ASC, id ASC'
        );
        $stmt->execute([$employeeId, $startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function computeDailyHoursFromEvents(array $events, ?\DateTimeImmutable $fallbackEnd = null): ?float
    {
        if (count($events) === 0) {
            return null;
        }

        $firstIn = null;
        $lastOut = null;
        foreach ($events as $event) {
            if ($firstIn === null && $event['action'] === 'in') {
                $firstIn = $event['attendance_time'];
            }
            if ($event['action'] === 'out') {
                $lastOut = $event['attendance_time'];
            }
        }

        if ($firstIn === null) {
            return null;
        }

        if ($lastOut !== null) {
            $start = new \DateTimeImmutable($firstIn);
            $end = new \DateTimeImmutable($lastOut);

            return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 1);
        }

        if ($fallbackEnd === null) {
            return null;
        }

        $start = new \DateTimeImmutable($firstIn);
        return round(($fallbackEnd->getTimestamp() - $start->getTimestamp()) / 3600, 1);
    }

    // Recent events for the admin live feed.
    public function findRecentFeed(int $limit = 20): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT a.id, a.employee_id, a.action, a.attendance_time, '
            . "CONCAT_WS(' ', u.first_name, u.last_name) AS name "
            . 'FROM attendance a '
            . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
            . 'ORDER BY a.attendance_time DESC, a.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Computes hours between the first 'in' and last 'out' event of a day.
    public function computeDailyHours(string $employeeId, string $date): ?float
    {
        $stmt = Connection::get()->prepare(
            'SELECT attendance_time FROM attendance '
            . 'WHERE employee_id = ? AND DATE(attendance_time) = ? ORDER BY attendance_time ASC, id ASC'
        );
        $stmt->execute([$employeeId, $date]);
        $times = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($times) < 2) {
            return null;
        }

        $start = new \DateTimeImmutable($times[0]);
        $end = new \DateTimeImmutable($times[count($times) - 1]);

        return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 1);
    }
}