<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\AttendanceService;
use App\Services\TokenService;

class ClockController
{
    public function redeem(Request $request): Response
    {
        $token = (string) $request->input('token');

        try {
            // Clock events in the hosted schema are keyed by employee_id,
            // not user_id — for the Pi terminal's HMAC-token flow, the
            // employee_id is passed directly as the token payload.
            if ($token === '') {
                return Response::error('token is required', 400);
            }

            $payload = (new TokenService())->verify($token);
            $user = (new UserRepository())->findById((int) $payload['user_id']);
            if ($user === null) {
                return Response::error('Unknown employee', 401);
            }

            if ($user->role !== 'employee') {
                return Response::error('Forbidden', 403);
            }

            $result = (new AttendanceService())->toggle($user->employeeId, 'device');

            return Response::json([
                'action' => $result['action'],
                'employee_name' => $user->name,
                'employee_id' => $user->employeeId,
            ]);
        } catch (ValidationException $e) {
            return Response::error($e->getMessage(), 429);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    // Web-portal clock toggle — used by the frontend employee portal.
    public function toggle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        try {
            $result = (new AttendanceService())->toggle($user['employee_id'], 'manual');
        } catch (ValidationException $e) {
            return Response::error($e->getMessage(), 429);
        }

        return Response::json([
            'action' => $result['action'],
            'employee_id' => $user['employee_id'],
        ]);
    }
}
