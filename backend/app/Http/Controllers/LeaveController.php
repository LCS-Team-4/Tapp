<?php

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\LeaveRepository;
use App\Support\LeaveValidator;

class LeaveController
{
    public function __construct(private readonly LeaveRepository $repository = new LeaveRepository())
    {
    }

    public function submit(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $errors = LeaveValidator::validateSubmit($request->input() ?? []);
        if ($errors !== []) {
            return Response::json(['message' => 'Validation failed', 'errors' => $errors], 400);
        }

        $userRecord = $this->repository->findUser((int) $user['id']);
        if ($userRecord === null) {
            return Response::error('User not found', 403);
        }

        $input = $request->input() ?? [];
        $startDate = (string) ($input['start_date'] ?? '');
        $endDate = (string) ($input['end_date'] ?? '');
        if ($startDate !== '' && $endDate !== '') {
            $overlaps = $this->repository->findOverlaps((int) $userRecord['id'], $startDate, $endDate);
            if ($overlaps !== []) {
                return Response::error($this->overlapMessage($overlaps), 409);
            }
        }

        try {
            $leave = $this->repository->create((int) $userRecord['id'], $input);
        } catch (\RuntimeException $e) {
            return Response::json(['message' => 'Leave request could not be saved', 'error' => $e->getMessage()], 500);
        }

        if ($leave === []) {
            return Response::error('Insert failed, no data returned', 500);
        }

        return Response::json(['message' => 'Leave request submitted', 'data' => $leave], 201);
    }

    public function updateStatus(Request $request, string $leaveId): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        if (($user['role'] ?? null) !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $errors = LeaveValidator::validateStatusUpdate($request->input() ?? []);
        if ($errors !== []) {
            return Response::json(['message' => 'Validation failed', 'errors' => $errors], 400);
        }

        $existing = $this->repository->findById((int) $leaveId);
        if ($existing === null) {
            return Response::error('Leave request not found', 404);
        }

        $status = (string) ($request->input()['status'] ?? $request->input()['decision'] ?? '');
        $updated = $this->repository->updateStatus((int) $leaveId, $status);
        if ($updated === null) {
            return Response::error('Update failed, no data returned', 500);
        }

        return Response::json(['message' => 'Leave status updated', 'data' => $updated], 200);
    }

    public function getCalendar(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $errors = LeaveValidator::validateCalendarQuery($request->query());
        if ($errors !== []) {
            return Response::json(['message' => 'Validation failed', 'errors' => $errors], 400);
        }

        $calendar = $this->repository->fetchCalendar(
            (int) $user['id'],
            (string) ($user['role'] ?? 'employee'),
            $request->query('month') !== null ? (int) $request->query('month') : null,
            $request->query('year') !== null ? (int) $request->query('year') : null
        );

        return Response::json($this->groupCalendarByDate($calendar), 200);
    }

    public function getLeave(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $leave = $this->repository->listForUser((int) $user['id'], (string) ($user['role'] ?? 'employee'));

        return Response::json($leave, 200);
    }

    public function updateOwn(Request $request, string $leaveId): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $existing = $this->repository->findById((int) $leaveId);
        if ($existing === null) {
            return Response::error('Leave request not found', 404);
        }

        if ((int) ($existing['user_id'] ?? 0) !== (int) $user['id']) {
            return Response::error('Forbidden', 403);
        }

        if (($existing['status'] ?? '') !== 'pending') {
            return Response::error('Only pending leave requests can be edited', 403);
        }

        $errors = LeaveValidator::validateUpdateLeave($request->input() ?? []);
        if ($errors !== []) {
            return Response::json(['message' => 'Validation failed', 'errors' => $errors], 400);
        }

        $input = $request->input() ?? [];
        $startDate = (string) ($input['start_date'] ?? '');
        $endDate = (string) ($input['end_date'] ?? '');
        if ($startDate !== '' && $endDate !== '') {
            $overlaps = $this->repository->findOverlaps((int) $user['id'], $startDate, $endDate, (int) $leaveId);
            if ($overlaps !== []) {
                return Response::error($this->overlapMessage($overlaps), 409);
            }
        }

        $updated = $this->repository->update((int) $leaveId, $input);
        if ($updated === null) {
            return Response::error('Update failed, no data returned', 500);
        }

        return Response::json(['message' => 'Leave request updated', 'data' => $updated], 200);
    }

    public function cancel(Request $request, string $leaveId): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $existing = $this->repository->findById((int) $leaveId);
        if ($existing === null) {
            return Response::error('Leave request not found', 404);
        }

        if ((int) ($existing['user_id'] ?? 0) !== (int) $user['id']) {
            return Response::error('Forbidden', 403);
        }

        if (($existing['status'] ?? '') !== 'pending') {
            return Response::error('Only pending leave requests can be canceled', 403);
        }

        if (!$this->repository->delete((int) $leaveId)) {
            return Response::error('Cancel failed', 500);
        }

        return Response::json(['message' => 'Leave request canceled'], 200);
    }

    public function updateLeave(Request $request, string $leaveId): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        if (($user['role'] ?? null) !== 'admin') {
            return Response::error('Forbidden', 403);
        }

        $errors = LeaveValidator::validateUpdateLeave($request->input() ?? []);
        if ($errors !== []) {
            return Response::json(['message' => 'Validation failed', 'errors' => $errors], 400);
        }

        $existing = $this->repository->findById((int) $leaveId);
        if ($existing === null) {
            return Response::error('Leave request not found', 404);
        }

        $input = $request->input() ?? [];
        $startDate = (string) ($input['start_date'] ?? '');
        $endDate = (string) ($input['end_date'] ?? '');
        if ($startDate !== '' && $endDate !== '') {
            $overlaps = $this->repository->findOverlaps((int) ($existing['user_id'] ?? 0), $startDate, $endDate, (int) $leaveId);
            if ($overlaps !== []) {
                return Response::error($this->overlapMessage($overlaps), 409);
            }
        }

        $updated = $this->repository->update((int) $leaveId, $input);
        if ($updated === null) {
            return Response::error('Update failed, no data returned', 500);
        }

        return Response::json(['message' => 'Leave request updated', 'data' => $updated], 200);
    }

    // Builds a human-readable message listing the date ranges the user
    // already has leave booked for, e.g.:
    //   "You already have leave booked for 13 – 21 Aug (pending)."
    //   "You already have leave booked for 13 – 21 Aug (pending) and 5 – 7 Sep (approved)."
    private function overlapMessage(array $overlaps): string
    {
        $parts = [];
        foreach ($overlaps as $row) {
            $parts[] = $this->formatDateRange((string) ($row['start_date'] ?? ''), (string) ($row['end_date'] ?? ''))
                . ' (' . htmlspecialchars((string) ($row['status'] ?? 'pending')) . ')';
        }

        if (count($parts) === 1) {
            return 'You already have leave booked for ' . $parts[0] . '.';
        }

        $last = array_pop($parts);
        return 'You already have leave booked for ' . implode(', ', $parts) . ' and ' . $last . '.';
    }

    // Formats an ISO date range the same way the employee portal does:
    // "15 – 17 Jul", single-day "2 Jun", cross-month "30 Jul – 2 Aug".
    private function formatDateRange(string $startDate, string $endDate): string
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        [$sy, $sm, $sd] = array_map('intval', explode('-', $startDate));
        [$ey, $em, $ed] = array_map('intval', explode('-', $endDate));

        if ($startDate === $endDate) {
            return "{$sd} {$months[$sm - 1]}";
        }
        if ($sy === $ey && $sm === $em) {
            return "{$sd} – {$ed} {$months[$sm - 1]}";
        }
        return "{$sd} {$months[$sm - 1]} – {$ed} {$months[$em - 1]}";
    }

    private function groupCalendarByDate(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = $this->extractCalendarDate($row);
            $grouped[$key][] = $row;
        }

        ksort($grouped);

        return $grouped;
    }

    private function extractCalendarDate(array $row): string
    {
        $value = $row['start_date'] ?? null;
        if (!is_string($value) || $value === '') {
            return 'unknown';
        }

        return substr($value, 0, 10);
    }
}