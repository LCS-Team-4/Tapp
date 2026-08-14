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

    public function findAll(): array
    {
        $stmt = Connection::get()->query($this->baseQuery() . ' ORDER BY u.created_at ASC');

        return array_map(
            fn (array $row): array => $this->hydrate($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findByEmployeeId(string $employeeId): ?User
    {
        $stmt = Connection::get()->prepare($this->baseQuery() . ' WHERE u.employee_id = ?');
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    // Verifies login_id + password against the stored bcrypt hash. The hash
    // is fetched here for the comparison but never leaves this method on the
    // returned User object. Invalid credentials throw AuthException.
    public function verifyCredentials(string $loginId, string $password): User
    {
        $row = $this->fetchRow($loginId);

        if (!$row || $row['password'] === '' || !password_verify($password, $row['password'])) {
            throw new AuthException('Invalid credentials');
        }

        return $this->hydrate($row);
    }

    public function create(string $employeeId, string $firstName, string $lastName, string $email, string $password, string $role, ?string $department = null, ?string $position = null, bool $mustChangePassword = false): User
    {
        $rfidUid = $this->generateRfidUid($employeeId);
        $pdo = Connection::get();

        // Hosted DB uses role values "staff" / "admin". Some schemas use
        // "employee" instead of "staff" — try both if needed.
        $roleCandidates = $role === 'admin'
            ? ['admin']
            : array_values(array_unique([$role, 'staff', 'employee']));

        $lastError = null;
        $inserted = false;
        foreach ($roleCandidates as $roleValue) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (employee_id, rfid_uid, first_name, last_name, email, password, role, department, position, status) '
                    . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'OUT')"
                );
                $stmt->execute([
                    $employeeId, $rfidUid, $firstName, $lastName, $email,
                    $password, $roleValue, $department, $position,
                ]);
                $inserted = true;
                break;
            } catch (\PDOException $e) {
                $lastError = $e;
                // Retry with next role candidate on invalid enum / data errors.
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'role') || str_contains($msg, 'enum') || str_contains($msg, '1265') || str_contains($msg, '1366')) {
                    continue;
                }
                throw $e;
            }
        }

        if (!$inserted) {
            throw $lastError ?? new \RuntimeException('Unable to insert employee');
        }

        $newId = (int) $pdo->lastInsertId();

        if ($mustChangePassword) {
            try {
                $flagStmt = $pdo->prepare(
                    'UPDATE users SET must_change_password = 1 WHERE employee_id = ?'
                );
                $flagStmt->execute([$employeeId]);
            } catch (\PDOException) {
                // Column not present yet — ignore until migration is applied.
            }
        }

        try {
            $balanceStmt = $pdo->prepare(
                'INSERT INTO leave_balances (employee_id, annual_leave, sick_leave, stu_leave, fr_leave) '
                . 'VALUES (?, 15, 10, 4, 3) '
                . 'ON DUPLICATE KEY UPDATE employee_id = employee_id'
            );
            $balanceStmt->execute([$employeeId]);
        } catch (\PDOException) {
            // leave_balances table may not exist yet.
        }

        $created = $this->findByEmployeeId($employeeId);
        if ($created === null && $newId > 0) {
            $created = $this->findById($newId);
        }
        if ($created === null) {
            throw new \RuntimeException(
                'Employee was inserted but could not be reloaded (id=' . $newId . ', employee_id=' . $employeeId . ')'
            );
        }

        return $created;
    }

    public function emailExists(string $email): bool
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function employeeIdExists(string $employeeId): bool
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM users WHERE employee_id = ?');
        $stmt->execute([$employeeId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function updateByEmployeeId(string $employeeId, array $payload): ?User
    {
        $existing = $this->findByEmployeeId($employeeId);
        if ($existing === null) {
            return null;
        }

        $fields = [];
        $params = [];

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);
            if ($name !== '') {
                $parts = array_values(array_filter(preg_split('/\s+/', $name)));
                $fields[] = 'first_name = ?';
                $fields[] = 'last_name = ?';
                $params[] = $parts[0] ?? '';
                $params[] = $parts[1] ?? '';
            }
        }

        if (isset($payload['email'])) {
            $fields[] = 'email = ?';
            $params[] = trim((string) $payload['email']);
        }

        if (array_key_exists('department', $payload)) {
            $fields[] = 'department = ?';
            $params[] = $payload['department'] !== '' ? trim((string) $payload['department']) : null;
        }

        if (array_key_exists('position', $payload)) {
            $fields[] = 'position = ?';
            $params[] = $payload['position'] !== '' ? trim((string) $payload['position']) : null;
        }

        if ($fields === []) {
            return $existing;
        }

        $params[] = $existing->employeeId;
        $stmt = Connection::get()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE employee_id = ?');
        $stmt->execute($params);

        return $this->findByEmployeeId($existing->employeeId);
    }

    public function deleteByEmployeeId(string $employeeId): bool
    {
        $stmt = Connection::get()->prepare('DELETE FROM users WHERE employee_id = ?');
        $stmt->execute([$employeeId]);

        return $stmt->rowCount() > 0;
    }

    // Updates a user's role (staff -> admin, admin -> staff, etc.).
    // Accepts the hosted role vocabulary (staff/manager/admin) and maps it
    // the same way hydrate() does.
    public function updateRoleByEmployeeId(string $employeeId, string $role): ?User
    {
        $existing = $this->findByEmployeeId($employeeId);
        if ($existing === null) {
            return null;
        }

        $hostedRole = $role === 'admin' ? 'admin' : 'staff';

        $stmt = Connection::get()->prepare('UPDATE users SET role = ? WHERE employee_id = ?');
        $stmt->execute([$hostedRole, $employeeId]);

        return $this->findByEmployeeId($employeeId);
    }

    /**
     * Change a user's password and clear the must_change_password flag.
     * Returns true on success.
     */
    public function changePassword(int $userId, string $newPasswordHash): bool
    {
        try {
            $stmt = Connection::get()->prepare(
                'UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?'
            );
            $stmt->execute([$newPasswordHash, $userId]);
        } catch (\PDOException) {
            $stmt = Connection::get()->prepare(
                'UPDATE users SET password = ? WHERE id = ?'
            );
            $stmt->execute([$newPasswordHash, $userId]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Update only the contact email for an employee (self-service).
     */
    public function updateEmailById(int $userId, string $email): ?User
    {
        $stmt = Connection::get()->prepare('UPDATE users SET email = ? WHERE id = ?');
        $stmt->execute([trim($email), $userId]);

        return $this->findById($userId);
    }

    private function generateRfidUid(string $employeeId): string
    {
        // Preserve the same synthetic-rfid strategy the frontend already used:
        // "app-<employee_id>" — deterministic and unique via employee_id's UNIQUE key.
        return 'app-' . $employeeId;
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
        // Do not select must_change_password here so login never breaks
        // before the migration is applied. The flag is loaded in hydrate().
        return 'SELECT u.id, u.employee_id, u.rfid_uid, '
            . 'CONCAT_WS(\' \', u.first_name, u.last_name) AS name, '
            . 'u.email, u.password, u.role, u.department, u.position, u.status '
            . 'FROM users u';
    }

    private function fetchMustChangePassword(int $userId): bool
    {
        try {
            $stmt = Connection::get()->prepare(
                'SELECT must_change_password FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            $value = $stmt->fetchColumn();

            return $value !== false && (bool) $value;
        } catch (\PDOException) {
            return false;
        }
    }

    // Resolves the employee's annual leave balance from the leave_balances
    // table. The balance is the actual remaining days in the column itself
    // (the table stores remaining amounts, not allocated/used splits).
    private function fetchLeaveBalance(int $userId, string $employeeId): float
    {
        try {
            $stmt = Connection::get()->prepare(
                'SELECT annual_leave '
                . 'FROM leave_balances '
                . 'WHERE employee_id = ? LIMIT 1'
            );
            $stmt->execute([$employeeId]);
            $value = $stmt->fetchColumn();

            return $value === false ? 0.0 : (float) $value;
        } catch (\PDOException) {
            return 0.0;
        }
    }

    // Maps the hosted users.role enum (staff/manager/admin) and status
    // (IN/OUT) into the app's vocabulary the same way the old frontend
    // db_mysql.php did: staff -> employee, manager/admin -> admin.
    //
    // status is the live clock state (IN/OUT) from the hosted schema — it is
    // NOT an employment/account state. Every user is login-eligible
    // regardless of status. The AttendanceService relies on this field being
    // the real IN/OUT value as its single source of truth for clock state.
    private function hydrate(array $row): User
    {
        $hostedRole = $row['role'] ?? 'staff';
        $role = $hostedRole === 'staff' ? 'employee' : 'admin';

        return new User(
            id: (int) $row['id'],
            employeeId: $row['employee_id'],
            rfidId: $row['rfid_uid'],
            name: trim((string) $row['name']),
            email: $row['email'],
            role: $role,
            department: $row['department'] ?? null,
            position: $row['position'] ?? null,
            status: $row['status'] ?? 'OUT',
            annualLeaveBalance: $this->fetchLeaveBalance((int) $row['id'], (string) $row['employee_id']),
            mustChangePassword: $this->fetchMustChangePassword((int) $row['id']),
        );
    }
}