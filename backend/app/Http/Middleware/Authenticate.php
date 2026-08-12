<?php

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;

class Authenticate
{
    public function handle(Request $request, callable $next): Response
    {
        self::startSession();

        $userId = $_SESSION['user_id'] ?? null;

        if ($userId === null) {
            return Response::error('Unauthorized', 401);
        }

        $user = (new UserRepository())->findById((int) $userId);

        // users.status in the hosted schema is the clock state (IN/OUT),
        // not an employment/account state — every user is login-eligible
        // regardless of whether they are currently clocked in or out.
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $request->setUser($user->toArray());

        return $next($request);
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => config('app.env') === 'production',
        ]);

        session_start();
    }
}
