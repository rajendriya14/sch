<?php

namespace Services;

use RuntimeException;

/**
 * Lightweight SMTP Mail Service (zero external dependencies).
 * Supports SSL (port 465), STARTTLS (port 587), AUTH LOGIN, and multi-recipient HTML emails.
 */
class MailService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
            return;
        }

        $localPath = __DIR__ . '/../config/mail.credentials.local.php';
        if (file_exists($localPath)) {
            $this->config = require $localPath;
            return;
        }

        $dbConfig = self::loadDbConfig();
        if ($dbConfig !== null) {
            $this->config = $dbConfig;
            return;
        }

        throw new RuntimeException('Konfigurasi SMTP belum tersedia. Isi di Settings -> Email Reminder atau upload mail.credentials.local.php.');
    }

    public static function isConfigured(): bool
    {
        if (file_exists(__DIR__ . '/../config/mail.credentials.local.php')) {
            return true;
        }
        return self::loadDbConfig() !== null;
    }

    private static function loadDbConfig(): ?array
    {
        try {
            $db = \Config\Database::getInstance();
            $stmt = $db->query('SELECT * FROM app_mail_config WHERE id = 1 LIMIT 1');
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            if ($row && !empty($row['username']) && !empty($row['password'])) {
                return $row;
            }
        } catch (\Throwable $e) {
            // Ignore
        }
        return null;
    }

    /**
     * Send an HTML + Plain-Text (multipart/alternative) email to one or more recipients.
     *
     * @param string|string[] $to
     * @param array{important?: bool, reply_to?: string, text_body?: string} $options
     * @return array{success: bool, message: string}
     */
    public function send($to, string $subject, string $htmlBody, array $options = []): array
    {
        $recipients = is_array($to) ? $to : explode(',', (string) $to);
        $recipients = array_values(array_filter(array_map('trim', $recipients), static function (string $addr): bool {
            return filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
        }));

        if (empty($recipients)) {
            return ['success' => false, 'message' => 'Tidak ada alamat email tujuan yang valid.'];
        }

        $host       = (string) ($this->config['host'] ?? '');
        $port       = (int) ($this->config['port'] ?? 465);
        $encryption = strtolower((string) ($this->config['encryption'] ?? 'ssl'));
        $username   = (string) ($this->config['username'] ?? '');
        $password   = (string) ($this->config['password'] ?? '');
        $fromEmail  = (string) ($this->config['from_email'] ?? $username);
        $fromName   = (string) ($this->config['from_name'] ?? 'Ingetttt Coyyyy!');
        if ($fromName === '' || $fromName === 'Personal Notebook Assistant' || $fromName === 'Personal Notebook') {
            $fromName = 'Ingetttt Coyyyy!';
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            return ['success' => false, 'message' => "Gagal konek ke SMTP ({$remote}): {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, 15);

        try {
            $this->expectCode($socket, 220);
            $ehloHost = $_SERVER['SERVER_NAME'] ?? 'schedulewfm.iferror.web.id';
            $this->sendCommand($socket, "EHLO {$ehloHost}", 250);

            if ($encryption === 'tls') {
                $this->sendCommand($socket, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Gagal mengaktifkan enkripsi STARTTLS.');
                }
                $this->sendCommand($socket, "EHLO {$ehloHost}", 250);
            }

            if ($username !== '') {
                $this->sendCommand($socket, 'AUTH LOGIN', 334);
                $this->sendCommand($socket, base64_encode($username), 334);
                $this->sendCommand($socket, base64_encode($password), 235);
            }

            $this->sendCommand($socket, "MAIL FROM:<{$fromEmail}>", 250);
            foreach ($recipients as $rcpt) {
                $this->sendCommand($socket, "RCPT TO:<{$rcpt}>", [250, 251]);
            }

            $this->sendCommand($socket, 'DATA', 354);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $toHeader       = implode(', ', $recipients);
            $replyTo        = !empty($options['reply_to']) ? $options['reply_to'] : $fromEmail;
            $messageId      = '<' . bin2hex(random_bytes(12)) . '@' . ($host ?: 'iferror.web.id') . '>';
            $boundary       = '=_Part_' . bin2hex(random_bytes(12));

            $textBody = $options['text_body'] ?? trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'], "\n", $htmlBody))));
            // Dot-stuff lines starting with a single dot per RFC 5321
            $textBody = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $textBody));
            $textBody = str_replace("\n", "\r\n", $textBody);
            $htmlSafe = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $htmlBody));
            $htmlSafe = str_replace("\n", "\r\n", $htmlSafe);

            $headers = [
                "Date: " . date('r'),
                "Message-ID: {$messageId}",
                "From: {$encodedFrom} <{$fromEmail}>",
                "Reply-To: {$replyTo}",
                "To: {$toHeader}",
                "Subject: {$encodedSubject}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            ];

            if (!empty($options['important'])) {
                $headers[] = "X-Priority: 1 (Highest)";
                $headers[] = "X-MSMail-Priority: High";
                $headers[] = "Importance: High";
            }

            $bodyParts = [
                "--{$boundary}",
                "Content-Type: text/plain; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit",
                "",
                $textBody,
                "--{$boundary}",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit",
                "",
                $htmlSafe,
                "--{$boundary}--",
            ];

            $payload = implode("\r\n", $headers)
                . "\r\n\r\n"
                . implode("\r\n", $bodyParts)
                . "\r\n.";

            $this->sendCommand($socket, $payload, 250);
            $this->sendCommand($socket, 'QUIT', 221);
            fclose($socket);

            return ['success' => true, 'message' => 'Email berhasil dikirim ke ' . $toHeader];
        } catch (\Throwable $e) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param int|int[] $expectedCodes
     */
    private function sendCommand($socket, string $command, $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectCode($socket, $expectedCodes);
    }

    /**
     * @param int|int[] $expectedCodes
     */
    private function expectCode($socket, $expectedCodes): string
    {
        $expected = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        $response = '';

        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr(trim($response), 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("SMTP error (kode {$code}): " . trim($response));
        }

        return $response;
    }
}
