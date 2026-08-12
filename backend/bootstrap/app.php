<?php

use App\Http\Response;
use App\Http\Router;
use App\Support\Env;
use App\Support\Logger;

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        [$file, $prop] = array_pad(explode('.', $key, 2), 2, null);

        if (!array_key_exists($file, $cache)) {
            $path = __DIR__ . '/../config/' . $file . '.php';
            $cache[$file] = is_file($path) ? require $path : [];
        }

        return $prop === null ? $cache[$file] : ($cache[$file][$prop] ?? $default);
    }
}

// Registered before Env::load() so a missing/overwritten .env — the #1 cause
// of a blank 500 after deploy per deployment.md's troubleshooting section —
// is caught, logged, and turned into a generic JSON 500 instead of a blank page.
set_exception_handler(function (\Throwable $e): void {
    Logger::error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        Response::error('Internal server error', 500)->send();
    }
});

register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}");

        if (!headers_sent()) {
            Response::error('Internal server error', 500)->send();
        }
    }
});

$backendEnv = realpath(__DIR__ . '/../.env');
$rootEnv = realpath(__DIR__ . '/../../.env');

$envPath = null;
if ($backendEnv !== false && is_readable($backendEnv)) {
    $envPath = $backendEnv;
} elseif ($rootEnv !== false && is_readable($rootEnv)) {
    $envPath = $rootEnv;
} elseif ($backendEnv !== false) {
    $envPath = $backendEnv;
} elseif ($rootEnv !== false) {
    $envPath = $rootEnv;
}

if ($envPath === null) {
    Logger::error('Env: no .env path resolved; tried backend and root locations.');
    Env::load(__DIR__ . '/../.env');
} else {
    Logger::info('Env: loading .env from ' . $envPath);
    Env::load($envPath);
}

Logger::configure(__DIR__ . '/../' . Env::get('LOG_PATH', 'storage/logs/app.log'));

date_default_timezone_set((string) config('app.timezone', 'UTC'));

$router = new Router();
require __DIR__ . '/../routes/api.php';

return $router;
