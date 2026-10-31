<?php
namespace App\Core;

class Logger {
    protected static string $requestId = '';
    protected static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming)) {
            self::$requestId = $incoming;
        } else {
            self::$requestId = bin2hex(random_bytes(16));
        }

        self::$initialized = true;
    }

    public static function requestId(): string {
        if (!self::$initialized) {
            self::init();
        }
        return self::$requestId;
    }

    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void {
        self::init();

        $record = [
            'ts' => gmdate('c'),
            'level' => strtolower($level),
            'channel' => preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app',
            'request_id' => self::$requestId,
            'message' => $message,
            'context' => self::sanitize($context),
        ];

        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . $record['channel'] . '-' . gmdate('Y-m-d') . '.log';
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('[arthadesk:' . $record['level'] . '] ' . $message . ' ' . json_encode($record['context']));
        }
    }

    public static function debug(string $message, array $context = [], string $channel = 'app'): void {
        self::log('debug', $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void {
        self::log('info', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void {
        self::log('warning', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void {
        self::log('error', $message, $context, $channel);
    }

    public static function critical(string $message, array $context = [], string $channel = 'app'): void {
        self::log('critical', $message, $context, $channel);
    }

    public static function sanitize($value, string $key = '') {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::sanitize($v, (string)$k);
            }
            return $out;
        }

        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }

        if (is_string($value) && preg_match('/pass|secret|token|authorization|api[_-]?key|card|cvv|hash|csrf/i', $key)) {
            return '[redacted]';
        }

        if (is_string($value) && strlen($value) > 4000) {
            return substr($value, 0, 4000) . '... [truncated]';
        }

        return $value;
    }
}
