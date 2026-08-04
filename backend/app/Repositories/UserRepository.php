<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Exceptions\AuthException;
use App\Models\User;
use PDO;

// The only place SQL for `users` lives.
class UserRepository
{
    public function findByLoginId(string $loginId): ?User
    {
        $row = $this->fetchRow($loginId);

        return $row ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = Connection::get()->prepare($this->baseQuery() . ' WHERE u.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    // Verifies login_id + password against the stored bcrypt hash. The hash
    // is fetched here for the comparison but never leaves this method on the
    // returned User object. Invalid credentials and an inactive account both
    // throw the same AuthException so AuthController can't distinguish them
    // (avoids account enumeration).
    public function verifyCredentials(string $loginId, string $password): User
    {
        $row = $this->fetchRow($loginId);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            throw new AuthException('Invalid credentials');
        }

        if ($row['status'] !== 'active') {
            throw new AuthException('Invalid credentials');
        }

        return $this->hydrate($row);
    }

    private function fetchRow(string $loginId): array|false
    {
        $stmt = Connection::get()->prepare(
            $this->baseQuery() . ' WHERE u.employee_id = ? OR u.email = ?'
        );
        $stmt->execute([$loginId, $loginId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function baseQuery(): string
    {
        return 'SELECT u.id, u.employee_id, u.rfid_id, u.name, u.email, u.password_hash, '
            . 'u.role, d.name AS department, u.position, u.status, u.annual_leave_balance '
            . 'FROM users u LEFT JOIN departments d ON d.id = u.department_id';
    }

    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            employeeId: $row['employee_id'],
            rfidId: $row['rfid_id'],
            name: $row['name'],
            email: $row['email'],
            role: $row['role'],
            department: $row['department'],
            position: $row['position'],
            status: $row['status'],
            annualLeaveBalance: (float) $row['annual_leave_balance'],
        );
    }
}
