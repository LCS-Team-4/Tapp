<?php

namespace App\Services;

use App\Database\Connection;
use PDO;

// Pushes pending attendance events from the MySQL database to Google Sheets.
//
// This is called automatically from the admin dashboard API and the
// attendance feed — so whenever the admin page loads/refreshes, any new
// attendance events (from Pi RFID, web portal, manual entry) get pushed
// to Google Sheets immediately. No cron job required.
class AttendanceSyncService
{
    // Syncs all pending attendance events to Google Sheets.
    // Returns the number of events successfully synced.
    public function syncPendingEvents(): int
    {
        try {
            $pdo = Connection::get();
        } catch (\Throwable $e) {
            error_log('AttendanceSyncService: DB connection failed: ' . $e->getMessage());
            return 0;
        }

        // Check if Google Sheets sync is enabled in settings
        try {
            $settingsStmt = $pdo->query(
                'SELECT google_sheets_sync_enabled, google_sheets_webhook_url FROM settings WHERE id = 1'
            );
            $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('AttendanceSyncService: settings query failed: ' . $e->getMessage());
            return 0;
        }

        if (!$settings || empty($settings['google_sheets_sync_enabled']) || empty($settings['google_sheets_webhook_url'])) {
            return 0;
        }

        // Find all pending attendance events
        try {
            $stmt = $pdo->query(
                'SELECT a.id, a.employee_id, a.action, a.attendance_time, '
                . "CONCAT_WS(' ', u.first_name, u.last_name) AS employee_name "
                . 'FROM attendance a '
                . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
                . "WHERE a.sync_status = 'pending' "
                . 'ORDER BY a.attendance_time ASC, a.id ASC '
                . 'LIMIT 50'
            );
            $pendingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('AttendanceSyncService: pending events query failed: ' . $e->getMessage());
            return 0;
        }

        if (count($pendingEvents) === 0) {
            return 0;
        }

        $service = new GoogleSheetsService();
        $synced = 0;

        foreach ($pendingEvents as $event) {
            $eventType = $event['action'] === 'in' ? 'clock_in' : 'clock_out';
            $employeeName = $event['employee_name'] ?: $event['employee_id'];

            try {
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
                    $synced++;
                } else {
                    // Mark as failed (will be retried on next load)
                    $update = $pdo->prepare("UPDATE attendance SET sync_status = 'failed' WHERE id = ?");
                    $update->execute([$event['id']]);
                }
            } catch (\Throwable $e) {
                error_log('AttendanceSyncService: sync failed for event ' . $event['id'] . ': ' . $e->getMessage());
            }
        }

        if ($synced > 0) {
            error_log("AttendanceSyncService: synced {$synced} pending event(s) to Google Sheets");
        }

        return $synced;
    }

    // Syncs a single attendance event immediately (used when an event is
    // created through the backend). Returns true on success.
    public function syncEvent(int $eventId): bool
    {
        try {
            $pdo = Connection::get();
            $stmt = $pdo->prepare(
                'SELECT a.id, a.employee_id, a.action, a.attendance_time, '
                . "CONCAT_WS(' ', u.first_name, u.last_name) AS employee_name "
                . 'FROM attendance a '
                . 'LEFT JOIN users u ON u.employee_id = a.employee_id '
                . 'WHERE a.id = ?'
            );
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }

        if (!$event) {
            return false;
        }

        $eventType = $event['action'] === 'in' ? 'clock_in' : 'clock_out';
        $employeeName = $event['employee_name'] ?: $event['employee_id'];

        try {
            $service = new GoogleSheetsService();
            return $service->logEvent(
                $event['employee_id'],
                $employeeName,
                $eventType,
                'device'
            );
        } catch (\Throwable $e) {
            return false;
        }
    }
}