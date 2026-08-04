<?php

namespace App\Services;

use App\Database\Connection;
use App\Exceptions\ValidationException;
use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use App\Repositories\SettingsRepository;

// The tap state machine from docs/spec.md §6: first tap of the day clocks
// in, second tap clocks out and resolves present/late, third tap is
// rejected. Present/late resolution happens here, at clock-out — see §6 for
// why, and how this relates to mark_absences.php's onsite sweep.
class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository = new AttendanceRepository(),
        private readonly SettingsRepository $settingsRepository = new SettingsRepository(),
    ) {
    }

    public function redeem(int $userId, string $source): array
    {
        $workDate = date('Y-m-d');
        $existing = $this->attendanceRepository->findByUserAndDate($userId, $workDate);

        if ($existing === null) {
            $attendance = $this->attendanceRepository->createClockIn(
                $userId,
                $workDate,
                new \DateTimeImmutable(),
                $source
            );

            return ['action' => 'clocked_in', 'attendance' => $attendance];
        }

        if ($existing->clockOut !== null) {
            // Judgment call, not spec-derived: a third tap in one day is
            // rejected outright rather than treated as a correction to the
            // existing clock-out. Revisit if the team wants a
            // correction/override path instead.
            throw new ValidationException('Already clocked out for today');
        }

        $attendance = $this->resolveClockOut($existing, $workDate);

        return ['action' => 'clocked_out', 'attendance' => $attendance];
    }

    // Admin-only correction path for the rejected-third-tap case (docs/spec.md
    // §6) — no self-service fix, only an admin can correct via this method.
    // HTTP surface (the controller/route that will call this) is 6a's job,
    // alongside EmployeeController/LeaveController — not built here.
    public function correctRecord(
        int $attendanceId,
        int $adminUserId,
        ?string $clockIn,
        ?string $clockOut,
        ?string $status
    ): array {
        $clockInDt = $clockIn !== null ? new \DateTimeImmutable($clockIn) : null;
        $clockOutDt = $clockOut !== null ? new \DateTimeImmutable($clockOut) : null;

        if ($clockInDt !== null && $clockOutDt !== null && $clockOutDt <= $clockInDt) {
            throw new ValidationException('Clock-out must be after clock-in');
        }

        // recordManualCorrection() and the conditional recordClockOut() below
        // are two separate statements against the same row — wrapped in one
        // transaction so a failure between them can't leave corrected_by/
        // corrected_at stamped without the recomputed status/total_hours
        // actually applied.
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $attendance = $this->attendanceRepository->recordManualCorrection(
                $attendanceId,
                $adminUserId,
                $clockInDt,
                $clockOutDt,
                $status
            );

            // Admin didn't give an explicit status override, and the row now
            // has both times — resolve present/late the same way the normal
            // clock-out path does, rather than leaving a stale status behind.
            if ($status === null && $attendance->clockIn !== null && $attendance->clockOut !== null) {
                $resolved = $this->resolveStatusAndHours(
                    $attendance->workDate,
                    new \DateTimeImmutable($attendance->clockIn),
                    new \DateTimeImmutable($attendance->clockOut)
                );

                $attendance = $this->attendanceRepository->recordClockOut(
                    $attendance->id,
                    new \DateTimeImmutable($attendance->clockOut),
                    $resolved['status'],
                    $resolved['totalHours']
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['action' => 'corrected', 'attendance' => $attendance];
    }

    private function resolveClockOut(Attendance $existing, string $workDate): Attendance
    {
        $clockOut = new \DateTimeImmutable();
        $resolved = $this->resolveStatusAndHours($workDate, new \DateTimeImmutable($existing->clockIn), $clockOut);

        return $this->attendanceRepository->recordClockOut(
            $existing->id,
            $clockOut,
            $resolved['status'],
            $resolved['totalHours']
        );
    }

    // Shared by the normal clock-out path and correctRecord()'s recompute
    // branch, so the present/late grace-period math exists in exactly one
    // place.
    private function resolveStatusAndHours(string $workDate, \DateTimeImmutable $clockIn, \DateTimeImmutable $clockOut): array
    {
        $settings = $this->settingsRepository->get();

        $deadline = (new \DateTimeImmutable($workDate . ' ' . $settings['working_hours_start']))
            ->modify('+' . (int) $settings['late_threshold_minutes'] . ' minutes');

        $status = $clockIn <= $deadline ? 'present' : 'late';
        $totalHours = round(($clockOut->getTimestamp() - $clockIn->getTimestamp()) / 3600, 2);

        return ['status' => $status, 'totalHours' => $totalHours];
    }
}
