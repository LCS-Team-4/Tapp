<?php

namespace App\Models;

// Plain data holder matching the users table — deliberately excludes
// password_hash. This is what gets handed back toward the client; the hash
// must never leave the repository layer, let alone get serialized here.
class User implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $employeeId,
        public readonly ?string $rfidId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly ?string $department,
        public readonly ?string $position,
        public readonly string $status,
        public readonly float $annualLeaveBalance,
        public readonly bool $mustChangePassword = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employeeId,
            'rfid_id' => $this->rfidId,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'department' => $this->department,
            'position' => $this->position,
            'status' => $this->status,
            'annual_leave_balance' => $this->annualLeaveBalance,
            'must_change_password' => $this->mustChangePassword,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
