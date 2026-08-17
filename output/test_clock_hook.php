<?php
require __DIR__ . '/../backend/bootstrap/app.php';

$pdo = \App\Database\Connection::get();
$emp = $pdo->query("SELECT employee_id, status FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo 'TESTING WITH EMPLOYEE: ' . json_encode($emp) . PHP_EOL;

if ($emp) {
    $service = new \App\Services\AttendanceService();
    // Record the current status to restore later
    $originalStatus = $emp['status'];

    // Toggle the employee (clock in/out)
    $result = $service->toggle($emp['employee_id'], 'manual');
    echo 'TOGGLE RESULT: ' . json_encode($result) . PHP_EOL;

    // Toggle back to restore original state
    $result2 = $service->toggle($emp['employee_id'], 'manual');
    echo 'TOGGLE BACK RESULT: ' . json_encode($result2) . PHP_EOL;
}