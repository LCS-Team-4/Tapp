<?php
require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/bootstrap/app.php';

use App\Database\Connection;

$db = Connection::get();
$stmt = $db->prepare('SELECT id, employee_id, email, password, role, status FROM users WHERE email = ? OR employee_id = ?');
$stmt->execute(['sami@gmail.com', 'sami@gmail.com']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;
