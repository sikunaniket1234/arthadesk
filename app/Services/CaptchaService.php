<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;

class CaptchaService {
    public static function enabled(): bool {
        return (bool)Config::get('captcha.enabled', true);
    }

    public static function provider(): string {
        if (!self::enabled()) {
            return 'none';
        }

        $provider = (string)Config::get('captcha.provider', 'math');
        if ($provider === 'turnstile') {
            $siteKey = (string)Config::get('captcha.turnstile_site_key', '');
            $secret = (string)Config::get('captcha.turnstile_secret', '');
            if ($siteKey !== '' && $secret !== '') {
                return 'turnstile';
            }

            if (Config::get('app.env', 'development') === 'production') {
                Logger::critical('captcha_misconfigured', ['provider' => 'turnstile'], 'security');
                return 'turnstile';
            }

            return 'math';
        }

        return 'math';
    }

    public static function siteKey(): string {
        return (string)Config::get('captcha.turnstile_site_key', '');
    }

    public static function minFormSeconds(): int {
        return max(1, (int)Config::get('captcha.min_form_seconds', 2));
    }

    public static function prepare(): void {
        if (self::provider() !== 'math') {
            return;
        }

        $a = random_int(2, 9);
        $b = random_int(2, 9);
        Session::set('_captcha_math', [
            'question' => sprintf('What is %d + %d?', $a, $b),
            'answer' => (string)($a + $b),
            'issued_at' => time(),
        ]);
    }

    public static function question(): string {
        $challenge = Session::get('_captcha_math');
        if (empty($challenge['question'])) {
            self::prepare();
            $challenge = Session::get('_captcha_math');
        }
        return (string)($challenge['question'] ?? '');
    }

    public static function verify(Request $request): bool {
        if (!self::enabled()) {
            return true;
        }

        $honeypot = trim((string)$request->input('website', ''));
        if ($honeypot !== '') {
            Logger::warning('captcha_honeypot', ['ip' => AuditService::ip()], 'security');
            return false;
        }

        $formTs = (int)$request->input('form_ts', 0);
        $now = time();
        if ($formTs <= 0 || ($now - $formTs) < self::minFormSeconds() || ($now - $formTs) > 3600) {
            Logger::warning('captcha_timing_failed', ['form_ts' => $formTs, 'now' => $now], 'security');
            return false;
        }

        $provider = self::provider();
        if ($provider === 'turnstile') {
            return self::verifyTurnstile($request);
        }

        if ($provider === 'math') {
            $challenge = Session::get('_captcha_math');
            $answer = trim((string)$request->input('captcha_answer', ''));
            Session::remove('_captcha_math');

            if (empty($challenge['answer']) || empty($challenge['issued_at'])) {
                return false;
            }
            if ((time() - (int)$challenge['issued_at']) > (int)Config::get('captcha.math_ttl_seconds', 600)) {
                return false;
            }
            return $answer !== '' && hash_equals((string)$challenge['answer'], $answer);
        }

        return true;
    }

    protected static function verifyTurnstile(Request $request): bool {
        $token = trim((string)($request->input('cf-turnstile-response', '') ?: $request->header('CF-Turnstile-Response', '')));
        if ($token === '') {
            return false;
        }

        $secret = (string)Config::get('captcha.turnstile_secret', '');
        if ($secret === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => AuditService::ip(),
        ]);

        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                Logger::error('turnstile_curl_error', ['error' => curl_error($ch)], 'security');
            }
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                ],
            ]);
            $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        }

        if (!is_string($raw) || $raw === '') {
            return false;
        }

        $result = json_decode($raw, true);
        if (!is_array($result) || empty($result['success'])) {
            Logger::warning('turnstile_failed', [
                'error_codes' => $result['error-codes'] ?? [],
            ], 'security');
            return false;
        }

        $allowed = (array)Config::get('captcha.turnstile_hostnames', []);
        $hostname = (string)($result['hostname'] ?? '');
        if (!empty($allowed) && $hostname !== '' && !in_array($hostname, $allowed, true)) {
            Logger::warning('turnstile_hostname_mismatch', ['hostname' => $hostname], 'security');
            return false;
        }

        return true;
    }

    public static function reset(): void {
        Session::remove('_captcha_math');
    }
}
