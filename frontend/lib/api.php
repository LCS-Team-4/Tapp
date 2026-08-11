<?php
// api.php - frontend helper for talking to the backend API.
// All frontend data access goes through this file; the frontend never
// connects to MySQL directly anymore.

// Returns the backend API base URL.
// The backend is served from /Tapp/backend/public with the /api base path.
// Works from any frontend subdirectory (e.g. frontend/, frontend/employee/,
// frontend/admin/actions/) by locating the /frontend/ segment in SCRIPT_NAME.
function api_base_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (($pos = strpos($scriptName, '/frontend/')) !== false) {
        $base = substr($scriptName, 0, $pos);
    } else {
        $base = dirname($scriptName);
        if ($base === '/' || $base === '\\') {
            $base = '';
        }
        if (preg_match('#^(.*?)/(frontend|admin|employee)$#', $base, $matches)) {
            $base = $matches[1];
        }
    }

    $base = rtrim($base, '/') . '/backend/public/api';

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    return $scheme . '://' . $host . $base;
}

// Makes an HTTP request to the backend API using cURL.
// Returns [status, body_array] or throws RuntimeException on transport error.
function api_request(string $method, string $path, array $payload = []): array
{
    $url = api_base_url() . $path;
    $sessionWasActive = session_status() === PHP_SESSION_ACTIVE;

    // If the frontend script already has an active session, release its lock
    // before the backend request uses the same session cookie. This avoids the
    // deadlock where the backend waits for the frontend request to finish.
    if ($sessionWasActive) {
        session_write_close();
    }

    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];

    // Forward the session cookie so the backend can authenticate us.
    if (session_id() !== '') {
        $sessionCookie = session_name() . '=' . session_id();
        $headers[] = 'Cookie: ' . $sessionCookie;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ];

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $payload !== []) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('API request failed: ' . $error);
    }

    if ($sessionWasActive) {
        session_start();
    }

    $body = json_decode($response, true);
    if (!is_array($body)) {
        $body = ['error' => ['message' => 'Invalid response from server']];
    }

    return [$status, $body];
}

// GET helper.
function api_get(string $path): array
{
    return api_request('GET', $path);
}

// POST helper.
function api_post(string $path, array $payload = []): array
{
    return api_request('POST', $path, $payload);
}

// PUT helper.
function api_put(string $path, array $payload = []): array
{
    return api_request('PUT', $path, $payload);
}

// DELETE helper.
function api_delete(string $path): array
{
    return api_request('DELETE', $path);
}

// Extracts the data payload from an API response body.
// The backend wraps responses as {"data": ...} or {"error": {...}}.
function api_data(array $body): array
{
    return $body['data'] ?? [];
}

// Extracts the error message from an API response body.
function api_error_message(array $body): string
{
    return $body['error']['message'] ?? 'Unknown error';
}