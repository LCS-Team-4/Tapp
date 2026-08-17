<?php
// auth.php — session bootstrap + role guard, included by every protected page.
// The backend API uses the same PHP session (via forwarded cookies), so the
// session data here must stay in sync with what the backend wrote.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirects to the login page if the visitor isn't authenticated,
 * or to their own portal if they're logged in as the wrong role.
 */
function require_role(string $role): void
{
    // project root, works from /employee or /admin; str_replace guards against
    // dirname() returning a backslash on Windows, and rtrim strips the
    // trailing slash so root deployments don't end up with "//login.php"
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');

    if (empty($_SESSION['authenticated'])) {
        header('Location: ' . $base . '/login.php');
        exit;
    }

    // If we have a valid login but user_id was never copied into the frontend
    // session (older sessions), clear the broken state and send them to login
    // instead of bouncing portal ↔ login forever.
    if (empty($_SESSION['user_id'])) {
        $_SESSION = [];
        header('Location: ' . $base . '/login.php');
        exit;
    }

    $currentRole = $_SESSION['role'] ?? '';
    if ($currentRole !== $role) {
        $destination = $currentRole === 'admin' ? '/admin/portal.php' : '/employee/portal.php';
        header('Location: ' . $base . $destination);
        exit;
    }
}

function current_user(): array
{
    return [
        'name'       => $_SESSION['user_name']  ?? 'Guest',
        'role'       => $_SESSION['role']        ?? '',
        'role_label' => $_SESSION['role_label']  ?? '',
        'employee_id'=> $_SESSION['employee_id'] ?? '',
        'initials'   => $_SESSION['initials']    ?? '??',
    ];
}
