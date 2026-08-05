<?php

function db_mysql_load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value === '') {
            $value = '';
        } elseif (
            strlen($value) >= 2
            && ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'")))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

function db_mysql_load_env(): void
{
    if (getenv('DB_HOST') !== false && getenv('DB_NAME') !== false) {
        return;
    }

    $paths = [
        __DIR__ . '/../.env',
        __DIR__ . '/../../backend/.env',
        __DIR__ . '/../../backend/.env.example',
    ];

    foreach ($paths as $path) {
        if (is_readable($path)) {
            db_mysql_load_env_file($path);
        }
    }
}

function db_mysql_config(): array
{
    db_mysql_load_env();

    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'tapp_db',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ];
}

function db_mysql_connect(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = db_mysql_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset'],
        );

        try {
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Unable to connect to the authentication database.', 500, $e);
        }
    }

    return $pdo;
}

// Maps the app's login role (employee/admin) to the hosted users.role
// enum (staff/manager/admin). employee -> staff; manager and admin both
// count as admin.
function db_mysql_role_to_hosted(string $role): array
{
    return $role === 'admin' ? ['manager', 'admin'] : ['staff'];
}

// Normalizes a hosted users.role (staff/manager/admin) back to the app's
// role vocabulary (employee/admin).
function db_mysql_role_from_hosted(string $role): string
{
    return $role === 'staff' ? 'employee' : 'admin';
}

function db_mysql_find_user(string $loginId, string $role): ?array
{
    $roleList = db_mysql_role_to_hosted($role);
    $placeholders = implode(',', array_fill(0, count($roleList), '?'));

    $sql = 'SELECT u.id, u.employee_id, u.rfid_uid, '
        . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, "
        . 'u.email, u.password AS password_hash, u.role AS hosted_role, u.status '
        . 'FROM users u '
        . "WHERE (u.employee_id = ? OR u.email = ?) AND u.role IN ({$placeholders}) LIMIT 1";

    try {
        $stmt = db_mysql_connect()->prepare($sql);
        $stmt->execute(array_merge([$loginId, $loginId], $roleList));
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Database query failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to read authentication data.', 500, $e);
    }

    if (!$user) {
        return null;
    }

    // Hosted users.status is the clock state (IN/OUT), not an employment
    // state — every user is login-eligible regardless of that value.
    return db_mysql_shape_user($user);
}

function db_mysql_employee(string $employeeId): ?array
{
    $sql = 'SELECT u.id, u.employee_id, u.rfid_uid, '
        . "CONCAT_WS(' ', u.first_name, u.last_name) AS name, "
        . 'u.email, u.role AS hosted_role, u.status '
        . 'FROM users u '
        . 'WHERE u.employee_id = ? LIMIT 1';

    try {
        $stmt = db_mysql_connect()->prepare($sql);
        $stmt->execute([$employeeId]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Database query failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to read employee data.', 500, $e);
    }

    return $user ? db_mysql_shape_user($user) : null;
}

// Turns a raw hosted users row into the shape the rest of the app expects.
// The hosted schema has no department/position/annual_leave_balance columns
// and carries no employment-status field, so those default to null/0/
// 'active' respectively.
function db_mysql_shape_user(array $user): array
{
    $user['role']             = db_mysql_role_from_hosted($user['hosted_role'] ?? 'staff');
    $user['department']       = null;
    $user['position']         = $user['position'] ?? null;
    $user['status']           = 'active';
    $user['annual_leave_balance'] = 0.0;

    unset($user['hosted_role']);

    return $user;
}

function db_mysql_compute_initials(string $name): string
{
    $parts = array_filter(array_map('trim', explode(' ', $name)));

    if (count($parts) === 0) {
        return '??';
    }

    return strtoupper(implode('', array_map(fn ($part) => $part[0], $parts)));
}
