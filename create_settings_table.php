<?php
// SECURITY: This schema-migration script is disabled in production. It
// executes raw SQL against the database — it must never be accessible
// to unauthenticated users.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/backend/bootstrap/app.php';

$pdo = App\Database\Connection::get();

$sql = "CREATE TABLE IF NOT EXISTS settings (
    id tinyint unsigned NOT NULL DEFAULT 1,
    company_name varchar(150) NOT NULL DEFAULT 'TAPP Botanical Co.',
    working_hours_start time NOT NULL DEFAULT '08:00:00',
    working_hours_end time NOT NULL DEFAULT '17:00:00',
    late_threshold_minutes smallint unsigned NOT NULL DEFAULT 10,
    qr_clock_in_enabled tinyint(1) NOT NULL DEFAULT 1,
    google_sheets_sync_enabled tinyint(1) NOT NULL DEFAULT 0,
    google_sheets_webhook_url varchar(500) DEFAULT NULL,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$pdo->exec($sql);
$pdo->exec('INSERT IGNORE INTO settings (id) VALUES (1)');

echo "SETTINGS TABLE CREATED\n";
$row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo 'ROW: ' . json_encode($row) . "\n";