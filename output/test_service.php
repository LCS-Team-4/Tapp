<?php
require __DIR__ . '/../backend/vendor/autoload.php';
require __DIR__ . '/../backend/bootstrap/app.php';

use App\Services\GoogleSheetsService;

$service = new GoogleSheetsService();

echo "=== Testing logEvent() ===\n";
$result = $service->logEvent('TEST-SERVICE', 'Service Test', 'login', 'web');
echo "logEvent result: " . var_export($result, true) . "\n\n";

echo "=== Testing testConnection() ===\n";
$url = 'https://script.google.com/macros/s/AKfycbyq6dr35Edy_TfIpGeut0NYkgX-7x99dOImkshsAgvz88yJyvfLZBz45m2FDhWUNhywZw/exec';
[$ok, $message] = $service->testConnection($url);
echo "testConnection ok: " . var_export($ok, true) . "\n";
echo "testConnection message: $message\n";