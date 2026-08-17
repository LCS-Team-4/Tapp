<?php
require __DIR__ . '/../backend/bootstrap/app.php';

$sync = new \App\Services\AttendanceSyncService();
$count = $sync->syncPendingEvents();
echo "Synced: {$count} pending event(s)\n";