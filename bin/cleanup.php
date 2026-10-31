<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Config;

Config::load();

$dryRun = in_array('--dry-run', $argv, true);
$root = dirname(__DIR__);

$db = Config::get('database.connections.mysql', []);
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['host'] ?? 'localhost',
    $db['port'] ?? '3306',
    $db['database'] ?? '',
    $db['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, (string)($db['username'] ?? ''), (string)($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$auditRetentionDays = max(30, (int)(getenv('AUDIT_RETENTION_DAYS') ?: 365));
$jobs = [
    'expired idempotency keys' => [
        'sql' => "DELETE FROM idempotency_keys WHERE status IN ('completed','failed') AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
    ],
    'stale idempotency keys' => [
        'sql' => "DELETE FROM idempotency_keys WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
    ],
    'old login attempts' => [
        'sql' => "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
    ],
    'used password resets' => [
        'sql' => "DELETE FROM password_resets WHERE (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY)) OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
    ],
    'old audit logs' => [
        'sql' => "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL {$auditRetentionDays} DAY)",
    ],
];

foreach ($jobs as $label => $job) {
    try {
        if ($dryRun) {
            $matches = [];
            if (preg_match('/^DELETE FROM (\w+) WHERE (.*)$/is', $job['sql'], $matches)) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM {$matches[1]} WHERE {$matches[2]}")->fetchColumn();
                echo "[DRY-RUN] {$label}: {$count}\n";
            }
            continue;
        }

        $affected = $pdo->exec($job['sql']);
        echo "{$label}: {$affected}\n";
    } catch (Throwable $e) {
        echo "{$label}: skipped (" . $e->getMessage() . ")\n";
    }
}

$cacheDir = $root . '/storage/cache';
$cacheDeleted = 0;
foreach (glob($cacheDir . '/rl_*.json') ?: [] as $file) {
    if ((time() - (int)filemtime($file)) / 86400 > 1) {
        if ($dryRun) {
            $cacheDeleted++;
        } elseif (@unlink($file)) {
            $cacheDeleted++;
        }
    }
}
echo ($dryRun ? '[DRY-RUN] ' : '') . "rate-limit cache files: {$cacheDeleted}\n";

echo $dryRun ? "Dry run completed.\n" : "Cleanup completed.\n";
exit(0);
