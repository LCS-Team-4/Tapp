<?php
$url = 'https://script.google.com/macros/s/AKfycbyq6dr35Edy_TfIpGeut0NYkgX-7x99dOImkshsAgvz88yJyvfLZBz45m2FDhWUNhywZw/exec';
$payload = json_encode([
    'timestamp'     => date('c'),
    'employee_id'   => 'TEST-PHP-FINAL',
    'employee_name' => 'PHP Final Verification',
    'event_type'    => 'test',
    'source'        => 'manual',
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT      => 'TAPP-Sync/1.0',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Status: $status\n";
echo "Error: $error\n";
echo "Response: $response\n";

if ($response !== false && $status < 400) {
    echo "Result: PASS\n";
} else {
    echo "Result: FAIL\n";
}