<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class LeaveRepository
{
    public function findUser(int $userId): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, employee_id, role, status FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // Returns the pending/approved leave requests whose date ranges overlap
    // [startDate, endDate] (empty array = no overlap). $excludeLeaveId lets
    // an edit skip the request being edited itself.
    public function findOverlaps(int $userId, string $startDate, string $endDate, ?int $excludeLeaveId = null): array
    {
        $query = 'SELECT id, start_date, end_date, status FROM leave_requests '
            . 'WHERE employee_id = :user_id '
            . "AND status IN ('pending', 'approved') "
            . 'AND start_date <= :end_date AND end_date >= :start_date';
        $params = [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($excludeLeaveId !== null) {
            $query .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeLeaveId;
        }

        $stmt = Connection::get()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, array $payload): array
    {
        try {
            $type = $this->normalizeRequestType($payload['type'] ?? $payload['leave_type'] ?? $payload['request_type'] ?? null);

            $stmt = Connection::get()->prepare(
                'INSERT INTO leave_requests (employee_id, request_type, start_date, end_date, reason, status, created_at) '
                . "VALUES (:employee_id, :request_type, :start_date, :end_date, :reason, 'pending', NOW())"
            );
            $stmt->execute([
                'employee_id' => $userId,
                'request_type' => $type,
                'start_date' => $payload['start_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'reason' => $payload['reason'] ?? null,
            ]);

            $id = (int) Connection::get()->lastInsertId();

            return $this->findById($id) ?? [];
        } catch (\PDOException $e) {
            throw new \RuntimeException('Unable to create leave request: ' . $e->getMessage(), 0, $e);
        }
    }

    private function normalizeRequestType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $type = trim(strtolower($type));

        $aliases = [
            'leave' => 'leave',
            'annual' => 'annual',
            'sick' => 'sick',
            'unpaid' => 'unpaid',
            'other' => 'other',
            'emergency' => 'other',
            'fr_leave' => 'fr_leave',
            'fr' => 'fr_leave',
            'family' => 'fr_leave',
            'family_responsibility' => 'fr_leave',
            'family responsibility' => 'fr_leave',
            'stu_leave' => 'stu_leave',
            'stu' => 'stu_leave',
            'study' => 'stu_leave',
            'study_leave' => 'stu_leave',
            'study leave' => 'stu_leave',
        ];

        return $aliases[$type] ?? 'other';
    }

    // Maps the app's status vocabulary into the hosted enum
    // (pending/approved/rejected). 'declined' -> 'rejected'.
    public function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'declined') {
            return 'rejected';
        }
        return in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';
    }

    public function findById(int $leaveId): ?array
    {
        // lr.employee_id is the int FK into users.id; u.employee_id is the
        // human-readable "EMP-0001" string. Alias the FK as user_id so the
        // later u.employee_id doesn't overwrite it in the FETCH_ASSOC row.
        $stmt = Connection::get()->prepare(
            'SELECT lr.*, lr.employee_id AS user_id, lr.request_type AS leave_type, u.employee_id, '
            . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.email "
            . 'FROM leave_requests lr '
            . 'LEFT JOIN users u ON u.id = lr.employee_id WHERE lr.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $leaveId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateStatus(int $leaveId, string $status): ?array
    {
        $existing = $this->findById($leaveId);
        if ($existing === null) {
            return null;
        }

        $normalized = $this->normalizeStatus($status);
        $oldStatus = $this->normalizeStatus((string) ($existing['status'] ?? 'pending'));

        $stmt = Connection::get()->prepare('UPDATE leave_requests SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $leaveId, 'status' => $normalized]);

        // When a leave request is approved, deduct the days from the
        // employee's leave balance. If it was previously approved and is now
        // being rejected/cancelled, restore the days.
        if ($normalized === 'approved' && $oldStatus !== 'approved') {
            $this->deductLeaveBalance(
                (string) $existing['employee_id'],
                (string) ($existing['request_type'] ?? $existing['leave_type'] ?? ''),
                $this->durationDays((string) $existing['start_date'], (string) $existing['end_date'])
            );
        } elseif ($oldStatus === 'approved' && $normalized !== 'approved') {
            $this->restoreLeaveBalance(
                (string) $existing['employee_id'],
                (string) ($existing['request_type'] ?? $existing['leave_type'] ?? ''),
                $this->durationDays((string) $existing['start_date'], (string) $existing['end_date'])
            );
        }

        return $this->findById($leaveId);
    }

    public function fetchCalendar(int $userId, string $role, ?int $month = null, ?int $year = null): array
    {
        $query = 'SELECT lr.*, lr.request_type AS leave_type FROM leave_requests lr';
        $params = [];

        if ($role !== 'admin') {
            $query .= ' WHERE lr.employee_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($month !== null && $year !== null) {
            $where = $role === 'admin' ? ' WHERE ' : ' AND ';
            $query .= $where . ' MONTH(lr.start_date) = :month AND YEAR(lr.start_date) = :year';
            $params['month'] = $month;
            $params['year'] = $year;
        }

        $stmt = Connection::get()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForUser(int $userId, string $role): array
    {
        if ($role === 'admin') {
            $stmt = Connection::get()->prepare(
                'SELECT lr.*, lr.request_type AS leave_type, u.employee_id, '
                . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.email "
                . 'FROM leave_requests lr '
                . 'LEFT JOIN users u ON u.id = lr.employee_id ORDER BY lr.created_at DESC'
            );
            $stmt->execute();
        } else {
            $stmt = Connection::get()->prepare(
                'SELECT lr.*, lr.request_type AS leave_type, u.employee_id, '
                . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.email "
                . 'FROM leave_requests lr '
                . 'LEFT JOIN users u ON u.id = lr.employee_id WHERE lr.employee_id = :user_id ORDER BY lr.created_at DESC'
            );
            $stmt->execute(['user_id' => $userId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $leaveId, array $payload): ?array
    {
        $stmt = Connection::get()->prepare(
            'UPDATE leave_requests SET start_date = :start_date, end_date = :end_date, reason = :reason WHERE id = :id'
        );
        $stmt->execute([
            'id' => $leaveId,
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
            'reason' => $payload['reason'] ?? null,
        ]);

        return $this->findById($leaveId);
    }

    public function delete(int $leaveId): bool
    {
        $existing = $this->findById($leaveId);
        if ($existing === null) {
            return false;
        }

        // If the leave was already approved, restore the balance before deleting.
        if ($this->normalizeStatus((string) ($existing['status'] ?? 'pending')) === 'approved') {
            $this->restoreLeaveBalance(
                (string) $existing['employee_id'],
                (string) ($existing['request_type'] ?? $existing['leave_type'] ?? ''),
                $this->durationDays((string) $existing['start_date'], (string) $existing['end_date'])
            );
        }

        $stmt = Connection::get()->prepare('DELETE FROM leave_requests WHERE id = :id');
        return $stmt->execute(['id' => $leaveId]);
    }

    /**
     * Fetch the employee's leave balances from the leave_balances table.
     * Returns an array with keys: annual_leave, sick_leave, stu_leave, fr_leave.
     */
    public function getLeaveBalances(string $employeeId): array
    {
        try {
            $stmt = Connection::get()->prepare(
                'SELECT annual_leave, sick_leave, stu_leave, fr_leave '
                . 'FROM leave_balances WHERE employee_id = :employee_id LIMIT 1'
            );
            $stmt->execute(['employee_id' => $employeeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return [
                    'annual_leave' => 15,
                    'sick_leave' => 10,
                    'stu_leave' => 4,
                    'fr_leave' => 3,
                ];
            }

            return [
                'annual_leave' => (int) $row['annual_leave'],
                'sick_leave' => (int) $row['sick_leave'],
                'stu_leave' => (int) $row['stu_leave'],
                'fr_leave' => (int) $row['fr_leave'],
            ];
        } catch (\PDOException) {
            return [
                'annual_leave' => 15,
                'sick_leave' => 10,
                'stu_leave' => 4,
                'fr_leave' => 3,
            ];
        }
    }

    /**
     * Deduct the given number of days from the employee's leave balance
     * for the specified leave type.
     */
    public function deductLeaveBalance(string $employeeId, string $leaveType, int $days): void
    {
        $column = $this->balanceColumnForType($leaveType);
        if ($column === null || $days <= 0) {
            return;
        }

        try {
            $stmt = Connection::get()->prepare(
                "UPDATE leave_balances SET {$column} = GREATEST(0, {$column} - :days) "
                . 'WHERE employee_id = :employee_id'
            );
            $stmt->execute([
                'days' => $days,
                'employee_id' => $employeeId,
            ]);
        } catch (\PDOException) {
            // Balance table may not exist yet — silently ignore.
        }
    }

    /**
     * Restore the given number of days back to the employee's leave balance
     * (used when an approved leave is rejected/cancelled).
     */
    public function restoreLeaveBalance(string $employeeId, string $leaveType, int $days): void
    {
        $column = $this->balanceColumnForType($leaveType);
        if ($column === null || $days <= 0) {
            return;
        }

        try {
            $stmt = Connection::get()->prepare(
                "UPDATE leave_balances SET {$column} = {$column} + :days "
                . 'WHERE employee_id = :employee_id'
            );
            $stmt->execute([
                'days' => $days,
                'employee_id' => $employeeId,
            ]);
        } catch (\PDOException) {
            // Balance table may not exist yet — silently ignore.
        }
    }

    /**
     * Map a leave request type to the corresponding leave_balances column.
     */
    private function balanceColumnForType(string $leaveType): ?string
    {
        $normalized = $this->normalizeRequestType($leaveType);

        $map = [
            'annual' => 'annual_leave',
            'sick' => 'sick_leave',
            'stu_leave' => 'stu_leave',
            'fr_leave' => 'fr_leave',
        ];

        return $map[$normalized] ?? null;
    }

    private function durationDays(string $start, string $end): int
    {
        $s = new \DateTimeImmutable(substr($start, 0, 10));
        $e = new \DateTimeImmutable(substr($end, 0, 10));
        return $s->diff($e)->days + 1;
    }
}