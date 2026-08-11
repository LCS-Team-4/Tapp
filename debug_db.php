<?php
require __DIR__ . '/backend/bootstrap/app.php';
$pdo = App\Database\Connection::get();
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
echo "COLUMNS=" . implode(',', $cols) . "\n";
$stmt = $pdo->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
$stmt->execute(['sami@gmail.com']);
$hash = $stmt->fetchColumn();
if ($hash === false) {
    echo "USER NOT FOUND\n";
    exit(1);
}
$passwords = ['password123', 'Password123!', 'wrongpass', '1234', 'admin', 'test', 'password', 'Sami123!', 'Sami@123'];
foreach ($passwords as $pw) {
    echo $pw . ': ' . (password_verify($pw, $hash) ? 'OK' : 'NO') . "\n";
}
