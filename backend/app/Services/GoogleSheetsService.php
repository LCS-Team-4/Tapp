<?php

namespace App\Services;

use App\Repositories\SettingsRepository;

// Sends attendance/login/logout events to a Google Sheets spreadsheet via a
// Google Apps Script Web App URL. The Apps Script appends a row to the sheet
// for each event. This is a free integration — no Google Cloud project, no
// API keys, no OAuth tokens required.
//
// The webhook URL is stored in the settings table (google_sheets_webhook_url)
// and can be configured from the admin portal. Sync can be toggled on/off via
// settings.google_sheets_sync_enabled.
//
// All failures are swallowed (logged) so a broken webhook never breaks the
// login or clock flow.
class GoogleSheetsService
{
    private SettingsRepository $settings;

    public function __construct(?SettingsRepository $settings = null)
    {
        $this->settings = $settings ?? new SettingsRepository();
    }

    // Sends an event to the Google Sheet. Returns true on success, false on
    // failure or when sync is disabled / not configured.
    public function logEvent(string $employeeId, string $employeeName, string $eventType, string $source = 'web'): bool
    {
        $settings = $this->settings->get();

        // Sync must be enabled and a webhook URL configured.
        if (empty($settings['google_sheets_sync_enabled']) || empty($settings['google_sheets_webhook_url'])) {
            return false;
        }

        $url = (string) $settings['google_sheets_webhook_url'];

        // Validate the URL looks like a Google Apps Script Web App.
        if (!preg_match('#^https://script\.google\.com/macros/s/.*/exec$#', $url)) {
            error_log('GoogleSheetsService: invalid webhook URL');
            return false;
        }

        $payload = [
            'timestamp'     => date('c'),
            'employee_id'   => $employeeId,
            'employee_name' => $employeeName,
            'event_type'    => $eventType,
            'source'        => $source,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            // Google Apps Script web apps redirect /exec to a streaming
            // endpoint on script.googleusercontent.com that only accepts GET.
            // Using text/plain + letting the 302 become a GET is required.
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'TAPP-Sync/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            // XAMPP ships no CA bundle by default, so verification fails
            // on script.google.com. In production set a proper CA path.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            error_log('GoogleSheetsService: webhook failed status=' . $status . ' error=' . $error . ' response=' . substr((string) $response, 0, 200));
            return false;
        }

        return true;
    }

    // Sends a test event to verify the webhook URL works. Returns [ok, message].
    public function testConnection(string $url): array
    {
        if (!preg_match('#^https://script\.google\.com/macros/s/.*/exec$#', $url)) {
            return [false, 'Invalid URL. Must be a Google Apps Script Web App URL like https://script.google.com/macros/s/.../exec'];
        }

        $payload = [
            'timestamp'     => date('c'),
            'employee_id'   => 'TEST',
            'employee_name' => 'Test Connection',
            'event_type'    => 'test',
            'source'        => 'admin',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            // Google Apps Script web apps redirect /exec to a streaming
            // endpoint on script.googleusercontent.com that only accepts GET.
            // Using text/plain + letting the 302 become a GET is required.
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'TAPP-Sync/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            // XAMPP ships no CA bundle by default, so verification fails
            // on script.google.com. In production set a proper CA path.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [false, 'Connection failed: ' . $error];
        }

        if ($status >= 400) {
            return [false, 'Web App returned HTTP ' . $status . ': ' . substr((string) $response, 0, 200)];
        }

        return [true, 'Connection successful! A test row was added to your spreadsheet.'];
    }
}