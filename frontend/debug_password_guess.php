<?php
// SECURITY: This debug script is disabled in production. It tries to
// crack password hashes — it must never be accessible to unauthenticated users.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$hash = '$2y$12$2BWn2A6ZbmA.Jlzwp0EtXOJHd6Ex/tPeynF3SQrRZmsNcsATrUUYi';
$guesses = ['password', '123456', 'sami123', 'Sami123!', 'admin', 'tapp2026', 'welcome', 'pass@123', 'admin123', 'Tapp2024', 'Tapp2025', 'Tapp2026', 'qwerty', 'letmein'];
foreach ($guesses as $g) {
    if (password_verify($g, $hash)) {
        echo "MATCH={$g}\n";
        exit(0);
    }
}
echo "NO MATCH\n";