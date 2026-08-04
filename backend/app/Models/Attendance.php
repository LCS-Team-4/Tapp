<?php

namespace App\Models;

// Plain data holder matching the attendance table.
class Attendance implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $workDate,
        public readonly ?string $clockIn,
        public readonly ?string $clockOut,
        public readonly ?float $totalHours,
        public readonly string $status,
        public readonly string $source,
        public readonly ?int $correctedBy,
        public readonly ?string $correctedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'work_date' => $this->workDate,
            'clock_in' => $this->clockIn,
            'clock_out' => $this->clockOut,
            'total_hours' => $this->totalHours,
            'status' => $this->status,
            'source' => $this->source,
            'corrected_by' => $this->correctedBy,
            'corrected_at' => $this->correctedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
