<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Services\BackupService;

try {
    $backupService = new BackupService();

    $path = $backupService->createBackup();

    echo "Backup created successfully:\n";
    echo $path . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
