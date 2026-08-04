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
        $stmt = Connection::get()->query('SELECT * FROM settings WHERE id = 1');

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
