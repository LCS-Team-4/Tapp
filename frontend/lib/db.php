<?php
// db.php — shared read/write access to data/db.json, the mock database
// backing attendance and leave features until a real backend/database exists.
// Authentication is now backed by the real tapp_db MySQL schema.
require_once __DIR__ . '/db_mysql.php';

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

function db_find_user(string $loginId, string $role): ?array
{
    return db_mysql_find_user($loginId, $role);
}

function db_employee(string $employeeId): ?array
{
    return db_mysql_employee($employeeId);
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
