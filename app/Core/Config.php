<?php
namespace App\Core;

class Config {
    protected static array $items = [];
    protected static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) return;

        // Load .env if present
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }

        $configDir = dirname(__DIR__, 2) . '/config';
        if (is_dir($configDir)) {
            foreach (glob($configDir . '/*.php') as $file) {
                $key = basename($file, '.php');
                self::$items[$key] = require $file;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        self::load();
        $parts = explode('.', $key);
        $curr = self::$items;

        foreach ($parts as $part) {
            if (!is_array($curr) || !array_key_exists($part, $curr)) {
                return $default;
            }
            $curr = $curr[$part];
        }

        return $curr;
    }
}
