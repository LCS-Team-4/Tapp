<?php

namespace Tests\Integration;

use App\Database\Connection;
use App\Exceptions\ValidationException;
use App\Services\AttendanceService;
use PDO;
use PHPUnit\Framework\TestCase;

// Runs against the real local tapp_dev database (see .env), same one the
// dev seed populates. No mocking — this is the state machine's actual
// contract with MySQL, including the UNIQUE (user_id, work_date) guard.
class AttendanceServiceTest extends TestCase
{
    private PDO $pdo;
    private AttendanceService $service;
    private int $userId;
    private string $workDate;
    private array $originalSettings;

    protected function setUp(): void
    {
        parent::setUp();

        // Loads Env, defines config(), and sets the server timezone from
        // .env — the same bootstrap the real app runs, so workDate below
        // resolves against the app's configured timezone, not the CLI's.
        // Only need that setup, not the global exception handler it also
        // installs, so drop that back off immediately (PHPUnit flags an
        // un-restored handler as a risky test otherwise).
        if (!function_exists('config')) {
            require dirname(__DIR__, 2) . '/bootstrap/app.php';
            restore_exception_handler();
        }

        $this->pdo = Connection::get();
        $this->service = new AttendanceService();
        $this->userId = $this->fetchUserId('EMP-101');
        $this->workDate = date('Y-m-d');
        $this->originalSettings = $this->fetchSettings();

        $this->deleteAttendanceRow();
    }

    protected function tearDown(): void
    {
        $this->deleteAttendanceRow();
        $this->restoreSettings($this->originalSettings);

        parent::tearDown();
    }

    public function testFirstRedeemClocksIn(): void
    {
        $result = $this->service->redeem($this->userId, 'device');

        $this->assertSame('clocked_in', $result['action']);

        $attendance = $result['attendance'];
        $this->assertSame($this->userId, $attendance->userId);
        $this->assertSame('onsite', $attendance->status);
        $this->assertNotNull($attendance->clockIn);
        $this->assertNull($attendance->clockOut);
    }

    public function testSecondRedeemWithinGracePeriodResolvesPresent(): void
    {
        $this->updateSettings('08:00:00', 10);

        $this->service->redeem($this->userId, 'device');
        $this->setClockIn($this->workDate . ' 08:05:00');

        $result = $this->service->redeem($this->userId, 'device');

        $this->assertSame('clocked_out', $result['action']);

        $attendance = $result['attendance'];
        $this->assertSame('present', $attendance->status);
        $this->assertNotNull($attendance->clockOut);

        $expectedHours = round(
            (strtotime($attendance->clockOut) - strtotime($this->workDate . ' 08:05:00')) / 3600,
            2
        );
        $this->assertEqualsWithDelta($expectedHours, $attendance->totalHours, 0.01);
    }

    public function testSecondRedeemPastGracePeriodResolvesLate(): void
    {
        $this->updateSettings('08:00:00', 10);

        $this->service->redeem($this->userId, 'device');
        $this->setClockIn($this->workDate . ' 08:25:00');

        $result = $this->service->redeem($this->userId, 'device');

        $this->assertSame('clocked_out', $result['action']);
        $this->assertSame('late', $result['attendance']->status);
    }

    public function testThirdRedeemThrowsValidationException(): void
    {
        $this->updateSettings('08:00:00', 10);

        $this->service->redeem($this->userId, 'device');
        $this->setClockIn($this->workDate . ' 08:05:00');
        $this->service->redeem($this->userId, 'device');

        $before = $this->fetchAttendanceRow();

        try {
            $this->service->redeem($this->userId, 'device');
            $this->fail('Expected ValidationException on third redeem');
        } catch (ValidationException $e) {
            $this->assertSame('Already clocked out for today', $e->getMessage());
        }

        $after = $this->fetchAttendanceRow();
        $this->assertSame($before, $after);
    }

    public function testCorrectRecordResolvesStatusFromGracePeriodWhenOnlyClockOutGiven(): void
    {
        $this->updateSettings('08:00:00', 10);

        $created = $this->service->redeem($this->userId, 'device');
        $attendanceId = $created['attendance']->id;
        $this->setClockIn($this->workDate . ' 08:05:00');

        $adminUserId = $this->fetchUserId('ADM-001');
        $clockOut = $this->workDate . ' 17:00:00';

        // Only correcting clock_out — clock_in is left as whatever it
        // already was (set above to fall within the grace period).
        $result = $this->service->correctRecord($attendanceId, $adminUserId, null, $clockOut, null);

        $this->assertSame('corrected', $result['action']);

        $attendance = $result['attendance'];
        $this->assertSame('present', $attendance->status);
        $this->assertSame($this->workDate . ' 08:05:00', $attendance->clockIn);
        $this->assertSame($clockOut, $attendance->clockOut);
        $this->assertSame($adminUserId, $attendance->correctedBy);
        $this->assertNotNull($attendance->correctedAt);
        $this->assertEqualsWithDelta(8.92, $attendance->totalHours, 0.01);
    }

    public function testCorrectRecordUsesExplicitStatusOverrideAsIs(): void
    {
        $this->updateSettings('08:00:00', 10);

        $created = $this->service->redeem($this->userId, 'device');
        $attendanceId = $created['attendance']->id;
        $this->setClockIn($this->workDate . ' 08:25:00');

        $adminUserId = $this->fetchUserId('ADM-001');
        $clockOut = $this->workDate . ' 17:00:00';

        // Past the grace period, so the recompute path would resolve
        // 'late' — the explicit override should win instead.
        $result = $this->service->correctRecord($attendanceId, $adminUserId, null, $clockOut, 'present');

        $this->assertSame('present', $result['attendance']->status);
    }

    public function testCorrectRecordRejectsClockOutBeforeClockIn(): void
    {
        $this->updateSettings('08:00:00', 10);

        $created = $this->service->redeem($this->userId, 'device');
        $attendanceId = $created['attendance']->id;

        $before = $this->fetchAttendanceRow();

        try {
            $this->service->correctRecord(
                $attendanceId,
                $this->fetchUserId('ADM-001'),
                $this->workDate . ' 09:00:00',
                $this->workDate . ' 08:00:00',
                null
            );
            $this->fail('Expected ValidationException for clock-out before clock-in');
        } catch (ValidationException $e) {
            $this->assertSame('Clock-out must be after clock-in', $e->getMessage());
        }

        $after = $this->fetchAttendanceRow();
        $this->assertSame($before, $after);
    }

    private function fetchUserId(string $employeeId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE employee_id = ?');
        $stmt->execute([$employeeId]);

        return (int) $stmt->fetchColumn();
    }

    private function fetchSettings(): array
    {
        return $this->pdo->query('SELECT * FROM settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    }

    private function updateSettings(string $workingHoursStart, int $lateThresholdMinutes): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE settings SET working_hours_start = ?, late_threshold_minutes = ? WHERE id = 1'
        );
        $stmt->execute([$workingHoursStart, $lateThresholdMinutes]);
    }

    private function restoreSettings(array $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE settings SET company_name = ?, working_hours_start = ?, working_hours_end = ?, '
            . 'late_threshold_minutes = ?, qr_clock_in_enabled = ?, google_sheets_sync_enabled = ? WHERE id = 1'
        );
        $stmt->execute([
            $settings['company_name'],
            $settings['working_hours_start'],
            $settings['working_hours_end'],
            $settings['late_threshold_minutes'],
            $settings['qr_clock_in_enabled'],
            $settings['google_sheets_sync_enabled'],
        ]);
    }

    private function setClockIn(string $clockIn): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE attendance SET clock_in = ? WHERE user_id = ? AND work_date = ?'
        );
        $stmt->execute([$clockIn, $this->userId, $this->workDate]);
    }

    private function fetchAttendanceRow(): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM attendance WHERE user_id = ? AND work_date = ?'
        );
        $stmt->execute([$this->userId, $this->workDate]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function deleteAttendanceRow(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM attendance WHERE user_id = ? AND work_date = ?');
        $stmt->execute([$this->userId, $this->workDate]);
    }
}
