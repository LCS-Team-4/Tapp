<?php
// db.php — shared read/write access to data/db.json, the mock database
// backing auth and per-user data until a real backend/database exists.
// No other file should touch db.json directly; go through the helpers here.

define('DB_PATH', __DIR__ . '/../data/db.json');

function db_read(): array
{
    $json = file_get_contents(DB_PATH);
    return json_decode($json, true) ?? [];
}

function db_write(array $data): void
{
    file_put_contents(DB_PATH, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// Matches a login form submission against users.email OR users.id, scoped
// to the selected role tab — email comparison is case-insensitive (email
// addresses are), id comparison is exact (EMP-#### / ADM-#### format).
function db_find_user(string $loginId, string $role): ?array
{
    $db = db_read();
    foreach ($db['users'] ?? [] as $user) {
        if ($user['role'] !== $role) {
            continue;
        }
        if (strcasecmp($user['email'], $loginId) === 0 || $user['id'] === $loginId) {
            return $user;
        }
    }
    return null;
}

function db_employee(string $employeeId): ?array
{
    $db = db_read();
    foreach ($db['users'] ?? [] as $user) {
        if ($user['id'] === $employeeId) {
            return $user;
        }
    }
    return null;
}

// Appends one entry (e.g. ['time_label' => ..., 'employee_name' => ...,
// 'event_type' => ...]) to activity_log, read-modify-write. Not called
// anywhere yet — clock in/out and leave submit are still front-end only
// until the next prompt wires their actions through here.
function db_log_activity(array $entry): void
{
    $db = db_read();
    $db['activity_log'][] = $entry;
    db_write($db);
}
