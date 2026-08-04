<?php

namespace App\Http\Controllers;

use App\Exceptions\TokenException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\AttendanceService;
use App\Services\TokenService;

// No middleware here — the Pi terminal has no employee session, and per
// spec.md §10 V1 has no terminal-auth requirement. The token's own HMAC
// signature is the entire security boundary for this endpoint.
//
// Single-use/replay enforcement is NOT active here — TokenService::
// isConsumed()/markConsumed() are deliberately not called; they still throw
// ("not implemented — blocked on 004_create_tokens.sql, see spec.md §5"). A
// valid token could theoretically be redeemed more than once within its
// 30-second window; the practical blast radius is bounded by
// AttendanceService's own state machine (a second redemption just clocks
// the same person out, a third is rejected), but this is not the same
// guarantee real single-use enforcement would give.
class ClockController
{
    public function redeem(Request $request): Response
    {
        $token = (string) $request->input('token');
        // Accepted but not persisted yet — no TerminalRepository/devices
        // table write in this pass; that's step 6's TerminalRepository.
        $deviceName = $request->input('device_name');

        try {
            $payload = (new TokenService())->verify($token);
        } catch (TokenException $e) {
            return Response::error($e->getMessage(), 401);
        }

        $user = (new UserRepository())->findById((int) $payload['user_id']);

        if ($user === null || $user->status !== 'active') {
            return Response::error('Invalid or expired token', 401);
        }

        try {
            $result = (new AttendanceService())->redeem($user->id, 'device');
        } catch (ValidationException $e) {
            return Response::error($e->getMessage(), 409);
        }

        return Response::json([
            'action' => $result['action'],
            'employee_name' => $user->name,
            'time' => $result['action'] === 'clocked_in'
                ? $result['attendance']->clockIn
                : $result['attendance']->clockOut,
        ]);
    }
}
