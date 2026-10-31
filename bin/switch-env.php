<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Logger;
use App\Services\AuditService;

$root = dirname(__DIR__);
$active = $root . '/.env';

function readEnv(string $file): array
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

$args = array_slice($argv, 1);
$target = null;
$key = null;
$dryRun = false;
$force = false;

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with($arg, '--key=')) {
        $key = substr($arg, 6);
    } elseif (!str_starts_with($arg, '-') && $target === null) {
        $target = strtolower($arg);
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(2);
    }
}

if (!in_array($target, ['development', 'staging', 'production'], true)) {
    fwrite(STDERR, "Usage: php bin/switch-env.php <development|staging|production> --key=<ENV_SWITCH_KEY> [--dry-run] [--force]\n");
    exit(2);
}

if (!is_file($active)) {
    fwrite(STDERR, "Active .env not found.\n");
    exit(1);
}

$current = readEnv($active);
$currentEnv = strtolower((string)($current['APP_ENV'] ?? 'unknown'));
$expectedKey = (string)($current['ENV_SWITCH_KEY'] ?? getenv('ENV_SWITCH_KEY') ?: '');
$switchEnabled = strtolower((string)($current['ENV_SWITCH_ENABLED'] ?? 'false')) === 'true';

if (!$switchEnabled) {
    fwrite(STDERR, "Env switching is disabled (ENV_SWITCH_ENABLED=false).\n");
    exit(1);
}
if (strlen($expectedKey) < 32 || in_array($expectedKey, ['change-me', 'secret'], true)) {
    fwrite(STDERR, "ENV_SWITCH_KEY is missing or too short in the active .env.\n");
    exit(1);
}
if ($key === null || !hash_equals($expectedKey, $key)) {
    fwrite(STDERR, "Invalid environment switch key.\n");
    exit(1);
}

$templates = [
    'development' => $root . '/.env.dev',
    'staging' => $root . '/.env.staging',
    'production' => $root . '/.env.prod',
];
$template = $templates[$target];
if (!is_file($template)) {
    fwrite(STDERR, "Target template not found: " . basename($template) . "\n");
    exit(1);
}

$targetValues = readEnv($template);
if (strtolower((string)($targetValues['APP_ENV'] ?? '')) !== $target) {
    fwrite(STDERR, "Target template APP_ENV does not match '{$target}'.\n");
    exit(1);
}

if (in_array($target, ['production', 'staging'], true)) {
    if (($targetValues['APP_DEBUG'] ?? 'true') !== 'false') {
        fwrite(STDERR, "Refusing to switch: target template has APP_DEBUG enabled.\n");
        exit(1);
    }
    if (strlen((string)($targetValues['APP_KEY'] ?? '')) < 32) {
        fwrite(STDERR, "Refusing to switch: target template APP_KEY is missing or too short.\n");
        exit(1);
    }
    if (!str_starts_with((string)($targetValues['APP_URL'] ?? ''), 'https://')) {
        fwrite(STDERR, "Refusing to switch: target template APP_URL must use https.\n");
        exit(1);
    }
    if (in_array(strtolower((string)($targetValues['DB_PASS'] ?? '')), ['', 'password', 'root', 'arthapass', 'secure_password'], true)) {
        fwrite(STDERR, "Refusing to switch: target template has a default database password.\n");
        exit(1);
    }
}

$oldEnv = $currentEnv;
$timestamp = gmdate('Ymd-His');
$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Could not create backup directory.\n");
    exit(1);
}
$backup = $backupDir . '/env-' . $timestamp . '-' . $oldEnv;

if ($dryRun) {
    echo "Dry run: would switch {$oldEnv} -> {$target}\n";
    echo "Template: " . basename($template) . "\n";
    echo "Backup: storage/backups/" . basename($backup) . "\n";
    exit(0);
}

if ($oldEnv === $target) {
    if (!$force) {
        echo "Already running '{$target}'. Use --force to reapply the template.\n";
        exit(0);
    }
}

if (!copy($active, $backup)) {
    fwrite(STDERR, "Could not back up the current .env.\n");
    exit(1);
}
@chmod($backup, 0600);

$temp = $root . '/.env.tmp-' . bin2hex(random_bytes(6));
if (!copy($template, $temp)) {
    fwrite(STDERR, "Could not write temporary .env.\n");
    @unlink($temp);
    exit(1);
}
@chmod($temp, 0600);

if (!rename($temp, $active)) {
    fwrite(STDERR, "Could not replace .env atomically.\n");
    @unlink($temp);
    exit(1);
}
@chmod($active, 0600);

if (function_exists('opcache_reset')) {
    @opcache_reset();
}

try {
    AuditService::log('environment.switched', 'environment', null, [
        'old_env' => $oldEnv,
        'new_env' => $target,
        'forced' => $force,
    ], 0, 0);
} catch (Throwable $e) {
    Logger::warning('environment_switch_audit_failed', ['error' => $e->getMessage()], 'security');
}

echo "Environment switched: {$oldEnv} -> {$target}\n";
echo "Backup: storage/backups/" . basename($backup) . "\n";
echo "Restart PHP-FPM or the web service if OPcache is enabled.\n";
exit(0);
