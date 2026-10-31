<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Minimal dependency-free mailer.
 * Drivers: smtp (authenticated), mail (PHP mail()), log (development only).
 * Every failure is logged and swallowed — mail must never break a public form.
 */
class Mailer {
    public static function send(string $to, string $subject, string $body): bool {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('mailer_invalid_recipient', ['to' => '[redacted]'], 'mail');
            return false;
        }

        $cfg = Config::get('contact', []);
        $driver = strtolower((string)($cfg['mail_driver'] ?? 'log'));
        $from = self::fromAddress($cfg);
        $fromName = (string)($cfg['from_name'] ?? 'Artha-Desk Website');

        try {
            $ok = match ($driver) {
                'smtp' => self::smtp($cfg, $to, $subject, $body, $from, $fromName),
                'mail' => self::nativeMail($to, $subject, $body, $from, $fromName),
                default => self::logOnly($to, $subject, $body),
            };
        } catch (\Throwable $e) {
            Logger::error('mailer_failed', ['error' => $e->getMessage(), 'driver' => $driver], 'mail');
            $ok = false;
        }

        if ($ok) {
            Logger::info('mailer_sent', ['driver' => $driver, 'subject' => $subject], 'mail');
        }
        return $ok;
    }

    protected static function fromAddress(array $cfg): string {
        $from = trim((string)($cfg['from_email'] ?? ''));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        $user = trim((string)($cfg['smtp_user'] ?? ''));
        if ($user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            return $user;
        }
        return $cfg['email'] ?? '';
    }

    protected static function logOnly(string $to, string $subject, string $body): bool {
        $context = [
            'to_domain' => strstr($to, '@'),
            'subject' => $subject,
            'body_length' => strlen($body),
        ];
        // Only dump the message body when running in debug mode, so contact PII
        // never lands in production logs.
        if (Config::get('app.debug', false)) {
            $context['body'] = $body;
        }
        Logger::info('mailer_log_driver', $context, 'mail');
        return true;
    }

    protected static function nativeMail(string $to, string $subject, string $body, string $from, string $fromName): bool {
        if ($from === '') {
            throw new \RuntimeException('SMTP_FROM / FROM address is not configured.');
        }
        $headers = implode("\r\n", [
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'Reply-To: ' . $from,
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: ArthaDesk',
        ]);
        return @mail($to, $subject, $body, $headers);
    }

    protected static function smtp(array $cfg, string $to, string $subject, string $body, string $from, string $fromName): bool {
        $host = (string)($cfg['smtp_host'] ?? '');
        $user = (string)($cfg['smtp_user'] ?? '');
        $pass = (string)($cfg['smtp_pass'] ?? '');
        $enc = strtolower((string)($cfg['smtp_encryption'] ?? 'ssl'));
        $port = (int)($cfg['smtp_port'] ?? ($enc === 'tls' ? 587 : 465));

        if ($host === '' || $user === '' || $from === '') {
            throw new \RuntimeException('SMTP host, user and from address are required for the smtp driver.');
        }

        $useSsl = $enc === 'ssl';
        $remote = ($useSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("Cannot connect to {$host}:{$port} ({$errno} {$errstr})");
        }
        stream_set_timeout($fp, 15);

        try {
            self::expect($fp, 220);
            self::command($fp, 'EHLO ' . self::ehloDomain($from), 250);

            if (!$useSsl && $enc === 'tls') {
                self::command($fp, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }
                self::command($fp, 'EHLO ' . self::ehloDomain($from), 250);
            }

            if ($user !== '') {
                self::command($fp, 'AUTH LOGIN', 334);
                self::command($fp, base64_encode($user), 334);
                self::command($fp, base64_encode($pass), 235);
            }

            self::command($fp, 'MAIL FROM:<' . $from . '>', 250);
            self::command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($fp, 'DATA', 354);

            $message = self::buildMessage($to, $subject, $body, $from, $fromName);
            $payload = preg_replace('/^\./', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $message));
            fwrite($fp, rtrim((string)$payload, "\r\n") . "\r\n.\r\n");
            self::expect($fp, 250);

            @fwrite($fp, "QUIT\r\n");
        } finally {
            @fclose($fp);
        }

        return true;
    }

    protected static function buildMessage(string $to, string $subject, string $body, string $from, string $fromName): string {
        $headers = implode("\r\n", [
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::ehloDomain($from) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: ArthaDesk',
        ]);
        return $headers . "\r\n\r\n" . str_replace(["\r\n", "\r", "\n"], "\r\n", $body) . "\r\n";
    }

    protected static function ehloDomain(string $from): string {
        $domain = (string)substr((string)strrchr($from, '@'), 1);
        if ($domain !== '' && str_contains($domain, '.')) {
            return $domain;
        }
        return 'localhost';
    }

    /** Reads a full SMTP reply (handles multiline "250-" continuation). Returns [code, text]. */
    protected static function readReply($fp): array {
        $lines = [];
        while (($line = fgets($fp, 1024)) !== false) {
            $lines[] = trim($line);
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if ($lines === []) {
            throw new \RuntimeException('SMTP server closed the connection unexpectedly.');
        }
        return [(int)substr($lines[0], 0, 3), implode("\n", $lines)];
    }

    protected static function expect($fp, int $code): array {
        [$got, $text] = self::readReply($fp);
        if ($got !== $code) {
            throw new \RuntimeException("SMTP expected {$code}, got {$got}: " . substr($text, 0, 300));
        }
        return [$got, $text];
    }

    protected static function command($fp, string $command, $expected): void {
        fwrite($fp, $command . "\r\n");
        [$got, $text] = self::readReply($fp);
        $codes = is_array($expected) ? $expected : [$expected];
        if (!in_array($got, $codes, true)) {
            throw new \RuntimeException("SMTP command failed ({$command}): got {$got} — " . substr($text, 0, 300));
        }
    }
}
