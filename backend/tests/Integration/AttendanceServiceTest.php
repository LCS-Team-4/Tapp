<?php

namespace Tests\Integration;

use App\Database\Connection;
use App\Exceptions\ValidationException;
use App\Repositories\UserRepository;
use App\Services\AttendanceService;
use PDO;
use PHPUnit\Framework\TestCase;

// Runs against the real configured database (see .env). No mocking — this
// is the state machine's actual contract with MySQL, driving the live
// event-log schema (attendance rows keyed by employee_id, action 'in'/'out',
// users.status as the IN/OUT source of truth).
//
// Uses a dedicated, disposable test employee (TEST-EMP-001) so the test
// never touches real production employees' attendance or status.
class AttendanceServiceTest extends TestCase
{
    private const TEST_EMPLOYEE = 'TEST-EMP-001';

    private PDO $pdo;
    private AttendanceService $service;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('config')) {
            require dirname(__DIR__, 2) . '/bootstrap/app.php';
            restore_exception_handler();
        }

        $this->pdo = Connection::get();
        $this->service = new AttendanceService();
        $this->users = new UserRepository();

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testFirstToggleClocksIn(): void
    {
        $this->seedTestEmployee('OUT');

        $result = $this->service->toggle(self::TEST_EMPLOYEE);

        $this->assertSame('clocked_in', $result['action']);
        $this->assertSame('IN', $this->fetchStatus());
        $this->assertSame(1, $this->countEventsToday());
    }

    public function testSecondToggleAfterCooldownClocksOut(): void
    {
        $this->seedTestEmployee('OUT');
        $this->service->toggle(self::TEST_EMPLOYEE);
        $this->backdateLastEvent(60); // push last event beyond the cooldown

        $result = $this->service->toggle(self::TEST_EMPLOYEE);

        $this->assertSame('clocked_out', $result['action']);
        $this->assertSame('OUT', $this->fetchStatus());
        $this->assertSame(2, $this->countEventsToday());
    }

    public function testToggleWithinCooldownRejected(): void
    {
        $this->seedTestEmployee('OUT');
        $this->service->toggle(self::TEST_EMPLOYEE);

        try {
            $this->service->toggle(self::TEST_EMPLOYEE);
            $this->fail('Expected ValidationException for rapid toggle');
        } catch (ValidationException $e) {
            $this->assertSame('Please wait before scanning again', $e->getMessage());
        }
    }

    public function testClockInIsIdempotentWhenAlreadyIn(): void
    {
        $this->seedTestEmployee('IN');

        $result = $this->service->clockIn(self::TEST_EMPLOYEE);
        $this->backdateLastEvent(60);

        // Second clock-in while already IN should not create a duplicate row.
        $result = $this->service->clockIn(self::TEST_EMPLOYEE);

        $this->assertSame('clocked_in', $result['action']);
        $this->assertSame('IN', $this->fetchStatus());
        $this->assertSame(0, $this->countEventsToday());
    }

    public function testClockOutIsIdempotentWhenAlreadyOut(): void
    {
        $this->seedTestEmployee('OUT');

        $result = $this->service->clockOut(self::TEST_EMPLOYEE);

        $this->assertSame('clocked_out', $result['action']);
        $this->assertSame('OUT', $this->fetchStatus());
        $this->assertSame(0, $this->countEventsToday());
    }

    public function testToggleUnknownEmployee(): void
    {
        $result = $this->service->toggle('DOES-NOT-EXIST');

        $this->assertSame('unknown_employee', $result['action']);
    }

    // ---- helpers ----

    private function seedTestEmployee(string $status): void
    {
        $this->pdo->prepare(
            'INSERT INTO users (employee_id, rfid_uid, first_name, last_name, email, password, role, status) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            self::TEST_EMPLOYEE,
            'app-' . self::TEST_EMPLOYEE,
            'Test',
            'Employee',
            'test-' . self::TEST_EMPLOYEE . '@example.test',
            password_hash('test', PASSWORD_BCRYPT),
            'staff',
            $status,
        ]);
    }

    private function backdateLastEvent(int $seconds): void
    {
        $this->pdo->prepare(
            'UPDATE attendance SET attendance_time = DATE_SUB(NOW(), INTERVAL ? SECOND) '
            . 'WHERE employee_id = ? ORDER BY id DESC LIMIT 1'
        )->execute([$seconds, self::TEST_EMPLOYEE]);
    }

    private function fetchStatus(): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM users WHERE employee_id = ?');
        $stmt->execute([self::TEST_EMPLOYEE]);

        return (string) $stmt->fetchColumn();
    }

    private function countEventsToday(): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND DATE(attendance_time) = CURDATE()'
        );
        $stmt->execute([self::TEST_EMPLOYEE]);

        return (int) $stmt->fetchColumn();
    }

    private function cleanup(): void
    {
        $this->pdo->prepare('DELETE FROM attendance WHERE employee_id = ?')->execute([self::TEST_EMPLOYEE]);
        $this->pdo->prepare('DELETE FROM users WHERE employee_id = ?')->execute([self::TEST_EMPLOYEE]);
    }
}