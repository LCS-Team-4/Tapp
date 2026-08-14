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

    public function testWeekHoursLoggedAccumulatesAcrossWeek(): void
    {
        $this->seedTestEmployee('OUT');

        // Insert clock-in/out pairs on the previous two weekdays so the
        // weekly total is known without depending on today's events.
        $now = new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0, 0);
        $dayOfWeek = (int) $today->format('N');
        $weekStart = $dayOfWeek === 1
            ? $today
            : $today->modify('-' . ($dayOfWeek - 1) . ' days');

        $insert = $this->pdo->prepare(
            'INSERT INTO attendance (employee_id, action, attendance_time, check_in_method, sync_status, location, device_info) '
            . "VALUES (?, ?, ?, 'manual', 'synced', 'Main Entrance', 'Test')"
        );

        // Day 1: 08:00 -> 12:00 = 4.0 hrs
        $day1 = $weekStart->modify('+1 day');
        $insert->execute([self::TEST_EMPLOYEE, 'in', $day1->format('Y-m-d 08:00:00')]);
        $insert->execute([self::TEST_EMPLOYEE, 'out', $day1->format('Y-m-d 12:00:00')]);

        // Day 2: 09:00 -> 17:00 = 8.0 hrs
        $day2 = $weekStart->modify('+2 days');
        $insert->execute([self::TEST_EMPLOYEE, 'in', $day2->format('Y-m-d 09:00:00')]);
        $insert->execute([self::TEST_EMPLOYEE, 'out', $day2->format('Y-m-d 17:00:00')]);

        // No events today — the dashboard "This Week" card must still show
        // the accumulated 12.0 hours instead of a stale 0.
        $status = $this->service->todayStatus(self::TEST_EMPLOYEE);

        $this->assertSame('absent', $status['status']);
        $this->assertSame(12.0, $status['week_hours_logged']);
        $this->assertSame(40, $status['week_hours_target']);
    }

    public function testWeekHoursLoggedIncludesTodayWhenOnsite(): void
    {
        $this->seedTestEmployee('OUT');

        $now = new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0, 0);
        $dayOfWeek = (int) $today->format('N');
        $weekStart = $dayOfWeek === 1
            ? $today
            : $today->modify('-' . ($dayOfWeek - 1) . ' days');

        $insert = $this->pdo->prepare(
            'INSERT INTO attendance (employee_id, action, attendance_time, check_in_method, sync_status, location, device_info) '
            . "VALUES (?, ?, ?, 'manual', 'synced', 'Main Entrance', 'Test')"
        );

        // Yesterday: 08:00 -> 12:00 = 4.0 hrs
        $yesterday = $today->modify('-1 day');
        $insert->execute([self::TEST_EMPLOYEE, 'in', $yesterday->format('Y-m-d 08:00:00')]);
        $insert->execute([self::TEST_EMPLOYEE, 'out', $yesterday->format('Y-m-d 12:00:00')]);

        // Today: clocked in at 08:00 and still onsite (no 'out' yet).
        // The fallback end time counts up to "now", so the total must be
        // at least 4.0 hrs (yesterday) plus whatever has elapsed today.
        $insert->execute([self::TEST_EMPLOYEE, 'in', $today->format('Y-m-d 08:00:00')]);

        $status = $this->service->todayStatus(self::TEST_EMPLOYEE);

        $this->assertSame('onsite', $status['status']);
        $this->assertGreaterThanOrEqual(4.0, $status['week_hours_logged']);
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