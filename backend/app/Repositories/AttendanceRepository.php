<?php

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Attendance;
use PDO;

// The only place SQL for `attendance` lives.
class AttendanceRepository
{
    public function findByUserAndDate(int $userId, string $workDate): ?Attendance
    {
        $stmt = Connection::get()->prepare(
            $this->baseQuery() . ' WHERE user_id = ? AND work_date = ?'
        );
        $stmt->execute([$userId, $workDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    // Relies on the UNIQUE (user_id, work_date) constraint as the natural
    // guard against duplicate clock-in rows. A constraint violation surfaces
    // as a real PDOException rather than being swallowed here, since it
    // should never actually fire — AttendanceService checks for an existing
    // row before calling this.
    public function createClockIn(int $userId, string $workDate, \DateTimeImmutable $clockIn, string $source): Attendance
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO attendance (user_id, work_date, clock_in, status, source) '
            . "VALUES (?, ?, ?, 'onsite', ?)"
        );
        $stmt->execute([$userId, $workDate, $clockIn->format('Y-m-d H:i:s'), $source]);

        return $this->findById((int) $pdo->lastInsertId());
    }

    public function recordClockOut(int $attendanceId, \DateTimeImmutable $clockOut, string $status, float $totalHours): Attendance
    {
        $stmt = Connection::get()->prepare(
            'UPDATE attendance SET clock_out = ?, status = ?, total_hours = ? WHERE id = ?'
        );
        $stmt->execute([$clockOut->format('Y-m-d H:i:s'), $status, $totalHours, $attendanceId]);

        return $this->findById($attendanceId);
    }

    // Admin-only correction (docs/spec.md §6): only overwrites clock_in/
    // clock_out/status for whichever of the three the admin actually
    // supplied, via COALESCE — a correction to just the exit time leaves
    // the entry time alone. corrected_by/corrected_at/source are always
    // stamped, regardless of which fields changed.
    //
    // Deliberately does NOT recompute status/total_hours itself — that's
    // grace-period business logic (needs settings), which belongs in
    // AttendanceService alongside the identical math the normal clock-out
    // path already uses. AttendanceService::correctRecord() makes a
    // follow-up recordClockOut() call when a recompute is warranted.
    public function recordManualCorrection(
        int $attendanceId,
        int $adminUserId,
        ?\DateTimeImmutable $clockIn,
        ?\DateTimeImmutable $clockOut,
        ?string $status
    ): Attendance {
        $stmt = Connection::get()->prepare(
            'UPDATE attendance SET '
            . 'clock_in = COALESCE(?, clock_in), '
            . 'clock_out = COALESCE(?, clock_out), '
            . 'status = COALESCE(?, status), '
            . "corrected_by = ?, corrected_at = ?, source = 'manual' "
            . 'WHERE id = ?'
        );
        $stmt->execute([
            $clockIn?->format('Y-m-d H:i:s'),
            $clockOut?->format('Y-m-d H:i:s'),
            $status,
            $adminUserId,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $attendanceId,
        ]);

        return $this->findById($attendanceId);
    }

    private function findById(int $id): Attendance
    {
        $stmt = Connection::get()->prepare($this->baseQuery() . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->hydrate($row);
    }

    private function baseQuery(): string
    {
        return 'SELECT id, user_id, work_date, clock_in, clock_out, total_hours, status, source, '
            . 'corrected_by, corrected_at FROM attendance';
    }

    private function hydrate(array $row): Attendance
    {
        return new Attendance(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            workDate: $row['work_date'],
            clockIn: $row['clock_in'],
            clockOut: $row['clock_out'],
            totalHours: $row['total_hours'] !== null ? (float) $row['total_hours'] : null,
            status: $row['status'],
            source: $row['source'],
            correctedBy: $row['corrected_by'] !== null ? (int) $row['corrected_by'] : null,
            correctedAt: $row['corrected_at'],
        );
    }
}
