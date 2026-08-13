<?php

namespace App\Services;

use App\Support\Env;
use App\Support\Logger;

class EmailService
{
    private ?string $smtpHost;
    private ?int $smtpPort;
    private ?string $smtpUser;
    private ?string $smtpPass;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        // Load from environment variables via Env::get() — the app's Env
        // class stores values in a static property, NOT in $_ENV, so reading
        // $_ENV directly always returned null and mail() was always used.
        $this->smtpHost = Env::get('SMTP_HOST') ?: null;
        $this->smtpPort = Env::get('SMTP_PORT') ? (int) Env::get('SMTP_PORT') : null;
        $this->smtpUser = Env::get('SMTP_USER') ?: null;
        $this->smtpPass = Env::get('SMTP_PASS') ?: null;
        $this->fromEmail = Env::get('MAIL_FROM_EMAIL') ?: 'noreply@tapp.local';
        $this->fromName = Env::get('MAIL_FROM_NAME') ?: 'TAPP System';
    }

    /**
     * Send a welcome email to a newly registered employee.
     *
     * @param string $employeeEmail    Employee's email address
     * @param string $employeeName     Employee's full name
     * @param string $employeeId       Employee ID
     * @param string $temporaryPassword Temporary password
     * @param string $department       Department (optional)
     * @param string $position         Position (optional)
     * @return bool True if sent successfully, false otherwise
     */
    public function sendWelcomeEmail(
        string $employeeEmail,
        string $employeeName,
        string $employeeId,
        string $temporaryPassword,
        ?string $department = null,
        ?string $position = null
    ): bool {
        $subject = 'Welcome to TAPP - Employee Account Created';

        $deptText = $department ? "Department: {$department}\n" : '';
        $posText = $position ? "Position: {$position}\n" : '';

        $body = "Dear {$employeeName},\n\n"
            . "Your employee account has been created in the TAPP (Time & Attendance Portal Platform) system.\n\n"
            . "**Account Details:**\n"
            . "Employee ID: {$employeeId}\n"
            . "Email: {$employeeEmail}\n"
            . "{$deptText}"
            . "{$posText}"
            . "Temporary Password: {$temporaryPassword}\n\n"
            . "Please log in to the system and change your password immediately for security purposes.\n\n"
            . "If you have any questions or need assistance, please contact your administrator.\n\n"
            . "Best regards,\n"
            . "TAPP Administration Team";

        // If SMTP is configured, use the SMTP client (works on XAMPP/Windows
        // where PHP's mail() function is typically not configured).
        if ($this->smtpHost && $this->smtpPort) {
            return $this->sendViaSmtp($employeeEmail, $subject, $body);
        }

        // Fallback to PHP's built-in mail() function if SMTP is not configured.
        Logger::warning('EmailService: SMTP not configured, falling back to mail()');
        return $this->sendViaMail($employeeEmail, $subject, $body);
    }

    /**
     * Send email using PHP's mail() function.
     * This is a simple fallback - in production, SMTP should be configured.
     */
    private function sendViaMail(string $toEmail, string $subject, string $body): bool
    {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $result = @mail($toEmail, $subject, $body, $headers);
        if (!$result) {
            Logger::error("EmailService: mail() failed to send to {$toEmail}");
        }
        return $result;
    }

    /**
     * Send email via SMTP using PHP's stream_socket_client.
     * Supports STARTTLS (port 587) and implicit TLS (port 465).
     * Works with Gmail SMTP (smtp.gmail.com:587) and most providers.
     */
    private function sendViaSmtp(string $toEmail, string $subject, string $body): bool
    {
        $host = $this->smtpHost;
        $port = $this->smtpPort;
        $user = $this->smtpUser;
        $pass = $this->smtpPass;

        if (!$user || !$pass) {
            Logger::error('EmailService: SMTP configured but SMTP_USER/SMTP_PASS missing');
            return false;
        }

        // Determine connection scheme
        $useTls = $port === 465; // implicit TLS
        $useStartTls = $port === 587; // STARTTLS

        $remote = $useTls ? "tls://{$host}:{$port}" : "{$host}:{$port}";
        $timeout = 30;

        $conn = @stream_socket_client($remote, $errno, $errstr, $timeout);
        if (!$conn) {
            Logger::error("EmailService: SMTP connection failed to {$host}:{$port} — {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($conn, $timeout);

        try {
            $this->smtpRead($conn); // 220 greeting

            // EHLO
            $this->smtpCommand($conn, "EHLO " . ($this->smtpHost ?? 'localhost'));

            // STARTTLS for port 587
            if ($useStartTls) {
                $this->smtpCommand($conn, 'STARTTLS');
                $crypto = @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    Logger::error('EmailService: STARTTLS negotiation failed');
                    fclose($conn);
                    return false;
                }
                // Re-EHLO after TLS
                $this->smtpCommand($conn, "EHLO " . ($this->smtpHost ?? 'localhost'));
            }

            // AUTH LOGIN
            $this->smtpCommand($conn, 'AUTH LOGIN');
            $this->smtpCommand($conn, base64_encode($user));
            $this->smtpCommand($conn, base64_encode($pass));

            // MAIL FROM
            $this->smtpCommand($conn, "MAIL FROM:<{$this->fromEmail}>");

            // RCPT TO
            $this->smtpCommand($conn, "RCPT TO:<{$toEmail}>");

            // DATA
            $this->smtpCommand($conn, 'DATA');

            // Build the full message
            $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n"
                . "To: {$toEmail}\r\n"
                . "Reply-To: {$this->fromEmail}\r\n"
                . "Subject: {$subject}\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "X-Mailer: TAPP-SMTP/1.0\r\n";

            $message = $headers . "\r\n" . $body;

            // Dot-stuffing: lines starting with '.' get an extra '.'
            $message = preg_replace('/^\./m', '..', $message);

            $this->smtpCommand($conn, $message . "\r\n.");

            // QUIT
            $this->smtpCommand($conn, 'QUIT');

            fclose($conn);
            Logger::info("EmailService: Welcome email sent to {$toEmail} via SMTP");
            return true;

        } catch (\Throwable $e) {
            Logger::error('EmailService: SMTP error — ' . $e->getMessage());
            @fclose($conn);
            return false;
        }
    }

    /**
     * Send a command to the SMTP server and read the response.
     * Throws on non-2xx/3xx responses.
     */
    private function smtpCommand($conn, string $command): string
    {
        fwrite($conn, $command . "\r\n");
        return $this->smtpRead($conn);
    }

    /**
     * Read the SMTP server response, validating the status code.
     * Throws on error responses (4xx/5xx).
     */
    private function smtpRead($conn): string
    {
        $response = '';
        $code = '';

        while (($line = fgets($conn, 515)) !== false) {
            $response .= $line;
            $code = substr($line, 0, 3);

            // A line with a space after the code means the response is complete
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($code === '' || $code[0] === '4' || $code[0] === '5') {
            throw new \RuntimeException("SMTP server error: " . trim($response));
        }

        return $response;
    }
}