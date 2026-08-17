<?php
// sync_google_sheets.php — Cron job that pushes new attendance events from
// the MySQL database to Google Sheets.
//
// How it works:
//   1. Finds all attendance events with sync_status = 'pending'
//   2. For each event, looks up the employee name from the users table
//   3. Pushes the event to Google Sheets via the GoogleSheetsService webhook
//   4. Marks the event as 'synced' on success, or 'failed' on error
//
// This catches ALL attendance events regardless of source (Pi RFID, web
// portal, manual entry) — as long as they're in the attendance table, they
// get pushed to Google Sheets.
//
// Usage:
//   Normal run (syncs only pending events):
//     php sync_google_sheets.php
//
//   One-time full sync (syncs ALL events, including ones already marked
//   as 'synced' but never actually pushed):
//     php sync_google_sheets.php --all
//
// Setup: Add this to your server's cron (e.g. every minute):
//   * * * * * php /path/to/Tapp/backend/cron/sync_google_sheets.php
//
// SECURITY: This script is CLI-only. It must never be accessible via HTTP.

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../bootstrap/app.php';

use App\Database\Connection;
use App\Services\GoogleSheetsService;

$pdo = Connection::get();

// Check if Google Sheets sync is enabled in settings
$settingsStmt = $pdo->query('SELECT google_sheets_sync_enabled, google_sheets_webhook_url FROM settings WHERE id = 1');
$settings = $settingsStmt->fetch(\PDO::FETCH_ASSOC);

if (!$settings || empty($settings['google_sheets_sync_enabled']) || empty($settings['google_sheets_webhook_url'])) {
    echo "Google Sheets sync is disabled or not configured. Skipping.\n";
    exit(0);
}

// Determine if this is a full sync (--all flag)
$fullSync = in_array('--all', $argv, true);

// Find attendance events to sync
if ($fullSync) {
    // Full sync: all events that haven't been successfully synced
    $stmt = $pdo->query(
        'SELECT a.id, a.employee_id, a.action, a.attendance_time, '
        . "CONCAT_WS(' ', u.first_name, u.last_name) AS employee_name "
        . 'FROM attendance a '
        . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
        . "WHERE a.sync_status IN ('pending', 'failed') "
        . 'ORDER BY a.attendance_time ASC, a.id ASC'
    );
    echo "Running FULL sync (all pending + failed events)...\n";
} else {
    // Normal sync: only pending events
    $stmt = $pdo->query(
        'SELECT a.id, a.employee_id, a.action, a.attendance_time, '
        . "CONCAT_WS(' ', u.first_name, u.last_name) AS employee_name "
        . 'FROM attendance a '
        . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
        . "WHERE a.sync_status = 'pending' "
        . 'ORDER BY a.attendance_time ASC, a.id ASC'
    );
}

$events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (count($events) === 0) {
    echo "No attendance events to sync.\n";
    exit(0);
}

echo "Found " . count($events) . " attendance event(s) to sync.\n";

$service = new GoogleSheetsService();
$synced = 0;
$failed = 0;

foreach ($events as $event) {
    $eventType = $event['action'] === 'in' ? 'clock_in' : 'clock_out';
    $employeeName = $event['employee_name'] ?: $event['employee_id'];

    echo "  Syncing: {$event['employee_id']} ({$employeeName}) - {$eventType} at {$event['attendance_time']}... ";

    $ok = $service->logEvent(
        $event['employee_id'],
        $employeeName,
        $eventType,
        'device'
    );

    if ($ok) {
        // Mark as synced
        $update = $pdo->prepare("UPDATE attendance SET sync_status = 'synced' WHERE id = ?");
        $update->execute([$event['id']]);
        echo "OK\n";
        $synced++;
    } else {
        // Mark as failed (will be retried on next run)
        $update = $pdo->prepare("UPDATE attendance SET sync_status = 'failed' WHERE id = ?");
        $update->execute([$event['id']]);
        echo "FAILED\n";
        $failed++;
    }
}

echo "Done. Synced: {$synced}, Failed: {$failed}\n";