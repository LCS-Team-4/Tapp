<?php
// stream_updates.php — Server-Sent Events (SSE) endpoint for the employee
// portal. Streams the /users/profile payload (scoped to the logged-in user)
// whenever anything changes: their clock in/out status, week hours, leave
// balances, leave request status (e.g. admin approved/declined), or their
// attendance history. No packages — plain PHP on the server, the browser's
// built-in EventSource API on the client.
//
// Lifecycle: this script streams for ~25s then ends. The client's EventSource
// auto-reconnects within ~3s, so from the user's perspective the connection
// is effectively continuous. (The cap keeps us safely under PHP's
// max_execution_time and avoids injecting a PHP warning into the stream.)

require_once __DIR__ . '/../auth.php';
require_role('employee');

// auth.php's require_role() only gates on being logged in, so enforce the
// employee role explicitly here too — this endpoint must stay user-scoped.
if (($_SESSION['role'] ?? '') !== 'employee') {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
    header('Location: ' . $base . '/login.php');
    exit;
}

require_once __DIR__ . '/../lib/api.php';

// Release the session write lock up front. api.php forwards the session
// cookie itself, and we must not hold the lock while cURL talks to the
// backend, otherwise the backend request would block waiting for us.
session_write_close();

// Stream for ~25s, then end so EventSource reconnects.
set_time_limit(0);
$runUntil = time() + 25;

// SSE framing headers.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // tell nginx (if present) not to buffer

@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

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

// Compact signature of the user-scoped profile — only emit when something
// actually changed to avoid needless re-renders.
function profile_signature(array $data): string
{
    $parts = [];

    $today = $data['today_status'] ?? [];
    $parts[] = implode('|', [
        $today['status'] ?? '',
        $today['clock_in'] ?? '',
        $today['clock_out'] ?? '',
        $today['total_hours'] ?? '',
        $today['week_hours_logged'] ?? 0,
    ]);

    $parts[] = implode('|', $data['leave_balances'] ?? [
        'annual_leave' => $data['leave_balance'] ?? 0,
    ]);

    $leave = $data['leave_requests'] ?? [];
    $leaveSig = [];
    foreach ($leave as $row) {
        // Include every field rendered in the leave cards. This makes an
        // edited pending request update in another open employee tab too,
        // rather than waiting for a status change.
        $leaveSig[] = implode(':', [
            $row['leave_id'] ?? '',
            $row['leave_type'] ?? '',
            $row['start_date'] ?? '',
            $row['end_date'] ?? '',
            $row['reason'] ?? '',
            $row['status'] ?? '',
            $row['decided_at'] ?? '',
        ]);
    }
    $parts[] = implode('|', $leaveSig);

    $history = $data['attendance_history'] ?? [];
    $histSig = [];
    foreach ($history as $row) {
        $histSig[] = ($row['date_label'] ?? '') . ':' . ($row['clock_in'] ?? '') . ':' . ($row['clock_out'] ?? '') . ':' . ($row['total_hours'] ?? '') . ':' . ($row['status'] ?? '');
    }
    $parts[] = implode('|', $histSig);

    return implode('||', $parts);
}

$lastSignature = null;
$lastPing = 0;

while (time() < $runUntil) {
    if (connection_aborted()) {
        break;
    }

    try {
        [$status, $body] = api_get('/users/profile');
        if ($status === 200 && is_array($body)) {
            $data = $body['data'] ?? [];

            $signature = profile_signature($data);
            if ($signature !== $lastSignature) {
                $lastSignature = $signature;
                sse_send('update', $data);
                $lastPing = time();
            }
        }
    } catch (Throwable $e) {
        error_log('SSE employee stream error: ' . $e->getMessage());
    }

    if (time() - $lastPing >= 15) {
        sse_ping();
        $lastPing = time();
    }

    sleep(2);
}
