<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Services\GoogleSheetsService;

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

        // Log the sign-in event to Google Sheets (if sync is enabled).
        try {
            (new GoogleSheetsService())->logEvent($user->employeeId, $user->name, 'login', 'web');
        } catch (\Throwable $e) {
            error_log('GoogleSheetsService login hook failed: ' . $e->getMessage());
        }

        return Response::json($user->toArray());
    }

    public function signup(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $employeeId = trim((string) $request->input('employee_id', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');

        if ($name === '' || $employeeId === '' || $email === '' || $password === '' || $password !== $passwordConfirm) {
            return Response::error('Please fill in all fields and make sure passwords match', 400);
        }

        if ($this->users->emailExists($email) || $this->users->employeeIdExists($employeeId)) {
            return Response::error('That email or employee ID already exists', 409);
        }

        // Public signups always create staff accounts. Admin accounts can
        // only be created by an existing admin via the admin-only
        // /api/admin/admins/invite endpoint — never through this public
        // endpoint. This prevents privilege escalation via the signup form.
        $hostedRole = 'staff';

        // Split name into first/last the same way the old frontend signup did.
        $nameParts = array_values(array_filter(array_map('trim', explode(' ', $name))));
        $firstName = $nameParts[0] ?? 'Unknown';
        $lastName = $nameParts[1] ?? '';

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $user = $this->users->create(
                $employeeId,
                $firstName,
                $lastName,
                $email,
                $passwordHash,
                $hostedRole
            );
        } catch (\Throwable $e) {
            return Response::error('Unable to create account: ' . $e->getMessage(), 500);
        }

        self::startSession();
        $_SESSION['user_id'] = $user->id;

        return Response::json($user->toArray(), 201);
    }

    public function logout(Request $request): Response
    {
        self::startSession();

        // Capture the user's identity before destroying the session so the
        // sign-out event can be logged to Google Sheets.
        $employeeId = (string) ($_SESSION['employee_id'] ?? '');
        $userName = (string) ($_SESSION['user_name'] ?? '');

        $_SESSION = [];
        session_destroy();

        // Log the sign-out event to Google Sheets (if sync is enabled).
        if ($employeeId !== '') {
            try {
                (new GoogleSheetsService())->logEvent($employeeId, $userName, 'logout', 'web');
            } catch (\Throwable $e) {
                error_log('GoogleSheetsService logout hook failed: ' . $e->getMessage());
            }
        }

        return Response::json(['logged_out' => true]);
    }

    public function session(Request $request): Response
    {
        return Response::json($request->user());
    }

    /**
     * Force password change (first login) or normal password update.
     * Clears must_change_password on success.
     * For first-login flow the current_password may be the temporary one.
     */
    public function changePassword(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Unauthorized', 401);
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $confirmPassword = (string) $request->input('confirm_password', '');

        if ($newPassword === '' || strlen($newPassword) < 8) {
            return Response::error('New password must be at least 8 characters', 400);
        }

        if ($newPassword !== $confirmPassword) {
            return Response::error('New password and confirmation do not match', 400);
        }

        // Verify current password (required even on first-login force change)
        try {
            $this->users->verifyCredentials(
                $user['email'] ?? $user['employee_id'] ?? '',
                $currentPassword
            );
        } catch (AuthException) {
            // Also try employee_id if email was used above
            try {
                $this->users->verifyCredentials(
                    $user['employee_id'] ?? '',
                    $currentPassword
                );
            } catch (AuthException) {
                return Response::error('Current password is incorrect', 401);
            }
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $ok = $this->users->changePassword((int) $user['id'], $hash);

        if (!$ok) {
            return Response::error('Unable to update password', 500);
        }

        return Response::json([
            'message' => 'Password updated successfully',
            'must_change_password' => false,
        ]);
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