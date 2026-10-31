<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$envFile = $root . '/.env';

function parseEnvFile(string $file): array
{
    $values = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "\"'");
    }
    return $values;
}

if (!is_file($envFile)) {
    fwrite(STDERR, "No .env file found at {$envFile}\n");
    exit(1);
}

$env = parseEnvFile($envFile);
$errors = [];
$warnings = [];

$get = static fn(string $key, string $default = ''): string => (string)($env[$key] ?? $default);

$appEnv = $get('APP_ENV', 'production');
$production = in_array($appEnv, ['production', 'staging'], true);

if (!in_array($appEnv, ['development', 'staging', 'production'], true)) {
    $errors[] = 'APP_ENV must be development, staging, or production.';
}

$appKey = $get('APP_KEY');
if ($appKey === '' || $appKey === 'base64:change-me') {
    $errors[] = 'APP_KEY is missing. Run: php bin/key.php';
} elseif (str_starts_with($appKey, 'base64:')) {
    $decoded = base64_decode(substr($appKey, 7), true);
    if ($decoded === false || strlen($decoded) < 32) {
        $errors[] = 'APP_KEY must contain at least 32 decoded bytes.';
    }
} elseif (strlen($appKey) < 32) {
    $errors[] = 'APP_KEY must be at least 32 characters.';
}

$appUrl = $get('APP_URL');
if ($appUrl === '') {
    $errors[] = 'APP_URL is required.';
} elseif ($production && !str_starts_with($appUrl, 'https://')) {
    $errors[] = 'APP_URL must use https outside development.';
}

if ($production && $get('APP_DEBUG', 'true') !== 'false') {
    $errors[] = 'APP_DEBUG must be false in production/staging.';
}

if ($production && in_array(strtolower($get('DB_PASS')), ['', 'password', 'root', 'arthapass', 'secure_password'], true)) {
    $errors[] = 'DB_PASS is empty or a known default.';
}

if ($get('DB_HOST') === '') {
    $errors[] = 'DB_HOST is required.';
}
if ($get('DB_NAME') === '') {
    $errors[] = 'DB_NAME is required.';
}
if ($get('DB_USER') === '') {
    $errors[] = 'DB_USER is required.';
}

if ($production) {
    $switchKey = $get('ENV_SWITCH_KEY');
    if ($switchKey !== '' && strlen($switchKey) < 32) {
        $errors[] = 'ENV_SWITCH_KEY must be at least 32 characters.';
    }
    if ($switchKey === '') {
        $warnings[] = 'ENV_SWITCH_KEY is empty; env switching will be unavailable.';
    }

    $captcha = strtolower($get('CAPTCHA_PROVIDER', 'math'));
    if (!in_array($captcha, ['turnstile', 'math'], true)) {
        $errors[] = 'CAPTCHA_PROVIDER must be turnstile or math.';
    }
    if ($captcha === 'math' && $get('CAPTCHA_ALLOW_MATH_IN_PRODUCTION', 'false') !== 'true') {
        $errors[] = 'CAPTCHA_PROVIDER must be turnstile in production.';
    }
    if ($captcha === 'turnstile') {
        if ($get('TURNSTILE_SITE_KEY') === '') {
            $errors[] = 'TURNSTILE_SITE_KEY is required for Turnstile.';
        }
        if ($get('TURNSTILE_SECRET') === '') {
            $errors[] = 'TURNSTILE_SECRET is required for Turnstile.';
        }
    }

    if ($get('AUTO_MIGRATE', 'false') !== 'false') {
        $warnings[] = 'AUTO_MIGRATE should be false in production.';
    }
    if ($get('SEED_DEMO', 'false') !== 'false') {
        $errors[] = 'SEED_DEMO must be false in production.';
    }
    if ($get('SMTP_HOST') === '') {
        $warnings[] = 'SMTP is not configured; password resets and email notices will not send.';
    }
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'session'];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = "Required PHP extension missing: {$extension}";
    }
}

$writableDirs = ['storage/logs', 'storage/cache', 'storage/uploads', 'storage/backups'];
foreach ($writableDirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path) || !is_writable($path)) {
        $warnings[] = "Directory is missing or not writable: {$dir}";
    }
}

echo "Environment: {$appEnv}\n";
echo "URL: {$appUrl}\n";
echo "PHP: " . PHP_VERSION . "\n";

foreach ($warnings as $warning) {
    echo "[WARN] {$warning}\n";
}
foreach ($errors as $error) {
    echo "[FAIL] {$error}\n";
}

if ($errors !== []) {
    echo "\nEnvironment check failed with " . count($errors) . " error(s).\n";
    exit(1);
}

echo "\nEnvironment check passed" . ($warnings ? ' with warnings' : '') . ".\n";
exit(0);
