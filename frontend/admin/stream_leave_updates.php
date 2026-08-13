<?php
// stream_leave_updates.php — Server-Sent Events (SSE) endpoint for the admin
// Leave tab. Replaces the 15s client-side polling with a long-lived HTTP
// stream: whenever the pending-leave state changes (a new request from an
// employee, or an admin approve/decline), the server broadcasts an "update"
// event carrying the latest pending + history lists. No packages — plain PHP
// on the server, the browser's built-in EventSource API on the client.
//
// Lifecycle: this script streams for ~25s then ends. The client's EventSource
// auto-reconnects within ~3s, so from the user's perspective the connection
// is effectively continuous. (The cap keeps us safely under PHP's
// max_execution_time and avoids injecting a PHP warning into the stream.)

require_once __DIR__ . '/../auth.php';
require_role('admin');

// auth.php's require_role() only gates on being logged in (the $role arg is
// currently unused there), so enforce the admin role explicitly here too —
// this endpoint must stay admin-only to match the /admin/dashboard data it
// streams.
if (($_SESSION['role'] ?? '') !== 'admin') {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
    header('Location: ' . $base . '/login.php');
    exit;
}

require_once __DIR__ . '/../lib/api.php';

// Release the session write lock up front. api.php forwards the session
// cookie itself (it builds the Cookie header from session_name()/session_id()),
// and we must not hold the lock while cURL talks to the backend, otherwise
// the backend request would block waiting for us to finish.
session_write_close();

// Stream for ~25s, then end so EventSource reconnects. Also try to lift the
// execution time limit (harmless if the host has it disabled).
set_time_limit(0);
$runUntil = time() + 25;

// SSE framing headers — must be sent before any output.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // tell nginx (if present) not to buffer

// Make sure nothing on the stack buffers our echo() output past flush().
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// ---- SSE helpers ----
function sse_send(string $event, array $data): void
{
    echo "event: {$event}\n";
    foreach (explode("\n", json_encode($data)) as $line) {
        echo "data: {$line}\n";
    }
    echo "\n";
    flush();
}

function sse_ping(): void
{
    echo ": ping\n\n";
    flush();
}

// Compact signature of the pending list — only emit a real update when this
// changes (new request, or a decision moved a card to history).
function pending_signature(array $pending): string
{
    $parts = [];
    foreach ($pending as $row) {
        $parts[] = ($row['leave_id'] ?? '') . ':' . ($row['status'] ?? '');
    }
    return implode('|', $parts);
}

$lastSignature = null;
$lastPing = 0;

while (time() < $runUntil) {
    // Stop if the browser went away.
    if (connection_aborted()) {
        break;
    }

    try {
        [$status, $body] = api_get('/admin/dashboard');
        if ($status === 200 && is_array($body)) {
            $data = $body['data'] ?? [];
            $pending = $data['pending_leave_requests'] ?? [];
            $history = $data['leave_history'] ?? [];

            $signature = pending_signature($pending);
            if ($signature !== $lastSignature) {
                $lastSignature = $signature;
                sse_send('update', [
                    'pending_leave_requests' => $pending,
                    'leave_history' => $history,
                ]);
                $lastPing = time(); // an update is as good as a heartbeat
            }
        }
    } catch (Throwable $e) {
        error_log('SSE leave stream error: ' . $e->getMessage());
    }

    // Heartbeat every ~15s so proxies / timeouts don't kill the idle stream.
    if (time() - $lastPing >= 15) {
        sse_ping();
        $lastPing = time();
    }

    sleep(2);
}