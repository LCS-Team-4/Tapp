<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

// The only place SQL for `settings` lives. No Models/Settings.php — this is
// a singleton config row (id=1, always exists per chk_settings_singleton),
// not a per-record entity, so an associative array is enough.
class SettingsRepository
{
    public function get(): array
    {
        // Defensive: if the settings table doesn't exist yet (e.g. the
        // migration hasn't been run), return defaults instead of crashing
        // the whole dashboard.
        try {
            $stmt = Connection::get()->query('SELECT * FROM settings WHERE id = 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? [] : $row;
        } catch (\PDOException) {
            return [];
        }
    }

    // Updates the singleton settings row (id=1). Only the columns present in
    // $payload are updated; unknown keys are ignored. Returns the full row
    // after the update.
    public function update(array $payload): array
    {
        $allowed = [
            'company_name',
            'working_hours_start',
            'working_hours_end',
            'late_threshold_minutes',
            'qr_clock_in_enabled',
            'google_sheets_sync_enabled',
        ];

        $fields = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $fields[] = "`{$key}` = ?";
                $params[] = $payload[$key];
            }
        }

        if ($fields !== []) {
            $params[] = 1;
            $stmt = Connection::get()->prepare(
                'UPDATE settings SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute($params);
        }

        return $this->get();
    }
}