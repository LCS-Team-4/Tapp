<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;

class AuthController
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function login(Request $request): Response
    {
        $loginId = trim((string) $request->input('login_id', ''));
        $password = (string) $request->input('password', '');

        if ($loginId === '' || $password === '') {
            return Response::error('Invalid credentials', 401);
        }

        try {
            $user = $this->users->verifyCredentials($loginId, $password);
        } catch (AuthException) {
            return Response::error('Invalid credentials', 401);
        }

        self::startSession();
        $_SESSION['user_id'] = $user->id;

        return Response::json($user);
    }

    public function logout(Request $request): Response
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();

        return Response::json(['logged_out' => true]);
    }

    public function session(Request $request): Response
    {
        return Response::json($request->user());
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
