<?php
require __DIR__ . '/../backend/bootstrap/app.php';

$pdo = App\Database\Connection::get();

echo "=== USERS TABLE ===\n";
$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ')\n';
}

echo "\n=== SETTINGS TABLE ===\n";
$cols2 = $pdo->query('SHOW COLUMNS FROM settings')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ')\n';
}

echo "\n=== SETTINGS ROW ===\n";
$row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT) . "\n";