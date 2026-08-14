<?php
/**
 * Local dev router for: php -S localhost:8000 router.php
 * Run this from the Tapp project root (the folder that contains frontend/ and backend/).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$root = __DIR__;

// --- Backend API ---
if (str_starts_with($uri, '/backend/public') || str_starts_with($uri, '/api')) {
    if (str_starts_with($uri, '/backend/public')) {
        $file = $root . $uri;
        if (is_file($file)) {
            return false; // static file
        }
    }
    $_SERVER['SCRIPT_NAME'] = '/backend/public/index.php';
    chdir($root . '/backend/public');
    require $root . '/backend/public/index.php';
    return true;
}

// --- Frontend ---
if ($uri === '/' || $uri === '') {
    header('Location: /login.php');
    exit;
}

$frontendFile = $root . '/frontend' . $uri;

// Static assets
if (is_file($frontendFile)) {
    $ext = strtolower(pathinfo($frontendFile, PATHINFO_EXTENSION));
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'map', 'json'], true)) {
        return false; // let built-in server serve it
    }
    if ($ext === 'php') {
        $_SERVER['SCRIPT_NAME'] = $uri;
        chdir(dirname($frontendFile));
        require $frontendFile;
        return true;
    }
    return false;
}

// Directory with index.php
if (is_dir($frontendFile)) {
    $index = rtrim($frontendFile, '/') . '/index.php';
    if (is_file($index)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '/index.php';
        chdir($frontendFile);
        require $index;
        return true;
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found: {$uri}\n";
echo "Looking for: {$frontendFile}\n";
return true;
