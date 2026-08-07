<?php

namespace App\Support;

class LeaveValidator
{
    public static function validateSubmit(array $payload): array
    {
        $errors = [];

        $type = $payload['type'] ?? null;
        $allowedTypes = ['annual', 'sick', 'unpaid', 'other'];
        if (!in_array($type, $allowedTypes, true)) {
            $errors['type'][] = 'Type must be one of: annual, sick, unpaid, other';
        }

        $start = $payload['start_date'] ?? null;
        $end = $payload['end_date'] ?? null;
        if (!self::isValidDateTime($start)) {
            $errors['start_date'][] = 'Start date must be a valid datetime value';
        }
        if (!self::isValidDateTime($end)) {
            $errors['end_date'][] = 'End date must be a valid datetime value';
        }

        if (self::isValidDateTime($start) && self::isValidDateTime($end)) {
            $startDt = new \DateTimeImmutable((string) $start);
            $endDt = new \DateTimeImmutable((string) $end);
            if ($endDt < $startDt) {
                $errors['end_date'][] = 'End date must be after start date';
            }
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            $errors['reason'][] = 'Must contain reason';
        }

        return $errors;
    }

    public static function validateStatusUpdate(array $payload): array
    {
        $errors = [];
        $status = $payload['status'] ?? null;
        $allowedStatuses = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowedStatuses, true)) {
            $errors['status'][] = 'Invalid status: it must be approved, rejected or pending';
        }

        return $errors;
    }

    public static function validateCalendarQuery(array $query): array
    {
        $errors = [];

        if (array_key_exists('month', $query)) {
            $month = filter_var($query['month'], FILTER_VALIDATE_INT);
            if ($month === false || $month < 1 || $month > 12) {
                $errors['month'][] = 'Month must be between 1 and 12';
            }
        }

        if (array_key_exists('year', $query)) {
            $year = filter_var($query['year'], FILTER_VALIDATE_INT);
            if ($year === false || $year < 1900) {
                $errors['year'][] = 'Year must be a valid year';
            }
        }

        return $errors;
    }

    public static function validateUpdateLeave(array $payload): array
    {
        $errors = [];

        $start = $payload['start_date'] ?? null;
        $end = $payload['end_date'] ?? null;
        if (!self::isValidDateTime($start)) {
            $errors['start_date'][] = 'Start date must be a valid datetime value';
        }
        if (!self::isValidDateTime($end)) {
            $errors['end_date'][] = 'End date must be a valid datetime value';
        }

        if (self::isValidDateTime($start) && self::isValidDateTime($end)) {
            $startDt = new \DateTimeImmutable((string) $start);
            $endDt = new \DateTimeImmutable((string) $end);
            if ($endDt < $startDt) {
                $errors['end_date'][] = 'End date must be after start date';
            }
        }

        return $errors;
    }

    private static function isValidDateTime(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}