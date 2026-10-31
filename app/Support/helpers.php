<?php

use App\Core\Config;
use App\Core\Session;
use App\Localization\Translator;

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replacements = []): string {
        return Translator::get($key, $replacements);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string {
        return Translator::getLocale();
    }
}

if (!function_exists('money_format_inr')) {
    function money_format_inr($amount): string {
        $num = (float)($amount ?? 0);
        return '₹ ' . number_format($num, 2, '.', ',');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $base = rtrim(Config::get('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');
        return $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('api_url')) {
    function api_url(string $path): string {
        $prefix = trim((string)Config::get('app.api_prefix', ''), '/');
        return '/app/' . ($prefix !== '' ? $prefix . '/' : '') . ltrim($path, '/');
    }
}

if (!function_exists('api_token')) {
    function api_token(): string {
        return (string)Session::get('api_token', '');
    }
}

if (!function_exists('can_access')) {
    function can_access(string $path): bool {
        $user = auth_user();
        return \App\Services\Authorization::can($user['role'] ?? null, $path);
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $default;
        }
        if ($val === 'true' || $val === '(true)') return true;
        if ($val === 'false' || $val === '(false)') return false;
        if ($val === 'null' || $val === '(null)') return null;
        return $val;
    }
}

if (!function_exists('auth_user')) {
    function auth_user() {
        return Session::get('user');
    }
}

if (!function_exists('current_tenant')) {
    function current_tenant() {
        return Session::get('tenant');
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void {
        Session::flash($type, $message);
    }
}

if (!function_exists('get_flashes')) {
    function get_flashes(): array {
        return Session::getFlashes();
    }
}
