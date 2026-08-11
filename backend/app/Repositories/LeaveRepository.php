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

    public function create(int $userId, array $payload): array
    {
        try {
            $type = $this->normalizeRequestType($payload['type'] ?? $payload['leave_type'] ?? $payload['request_type'] ?? null);

            $stmt = Connection::get()->prepare(
                'INSERT INTO leave_requests (user_id, request_type, start_date, end_date, reason, status, created_at) '
                . "VALUES (:user_id, :request_type, :start_date, :end_date, :reason, 'pending', NOW())"
            );
            $stmt->execute([
                'user_id' => $userId,
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
        $stmt = Connection::get()->prepare(
            'SELECT lr.*, lr.request_type AS leave_type, u.employee_id, '
            . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.email "
            . 'FROM leave_requests lr '
            . 'LEFT JOIN users u ON u.id = lr.user_id WHERE lr.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $leaveId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateStatus(int $leaveId, string $status): ?array
    {
        $normalized = $this->normalizeStatus($status);
        $stmt = Connection::get()->prepare('UPDATE leave_requests SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $leaveId, 'status' => $normalized]);

        return $this->findById($leaveId);
    }

    public function fetchCalendar(int $userId, string $role, ?int $month = null, ?int $year = null): array
    {
        $query = 'SELECT lr.*, lr.request_type AS leave_type FROM leave_requests lr';
        $params = [];

        if ($role !== 'admin') {
            $query .= ' WHERE lr.user_id = :user_id';
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
                . 'LEFT JOIN users u ON u.id = lr.user_id ORDER BY lr.created_at DESC'
            );
            $stmt->execute();
        } else {
            $stmt = Connection::get()->prepare(
                'SELECT lr.*, lr.request_type AS leave_type, u.employee_id, '
                . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.email "
                . 'FROM leave_requests lr '
                . 'LEFT JOIN users u ON u.id = lr.user_id WHERE lr.user_id = :user_id ORDER BY lr.created_at DESC'
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
        $stmt = Connection::get()->prepare('DELETE FROM leave_requests WHERE id = :id');
        return $stmt->execute(['id' => $leaveId]);
    }
}