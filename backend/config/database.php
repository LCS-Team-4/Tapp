<?php

use App\Support\Env;

$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$name = Env::get('DB_NAME', '');
$charset = 'utf8mb4';

return [
    'host' => $host,
    'port' => $port,
    'name' => $name,
    'user' => Env::get('DB_USER', ''),
    'pass' => Env::get('DB_PASS', ''),
    'charset' => $charset,
    'dsn' => "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
];
