<?php
$hash = '$2y$12$2BWn2A6ZbmA.Jlzwp0EtXOJHd6Ex/tPeynF3SQrRZmsNcsATrUUYi';
$guesses = ['password', '123456', 'sami123', 'Sami123!', 'admin', 'tapp2026', 'welcome', 'pass@123', 'admin123', 'Tapp2024', 'Tapp2025', 'Tapp2026', 'qwerty', 'letmein'];
foreach ($guesses as $g) {
    if (password_verify($g, $hash)) {
        echo "MATCH={$g}\n";
        exit(0);
    }
}
echo "NO MATCH\n";
