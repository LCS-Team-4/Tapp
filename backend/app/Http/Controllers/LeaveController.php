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

        $activeUser = $this->repository->findActiveUser((int) $user['id']);
        if ($activeUser === null || !in_array((string) ($activeUser['status'] ?? ''), ['active', 'IN'], true)) {
            return Response::error('User not found or inactive', 403);
        }

        try {
            $leave = $this->repository->create((int) $activeUser['id'], $request->input() ?? []);
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

        $updated = $this->repository->update((int) $leaveId, $request->input() ?? []);
        if ($updated === null) {
            return Response::error('Update failed, no data returned', 500);
        }

        return Response::json(['message' => 'Leave request updated', 'data' => $updated], 200);
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