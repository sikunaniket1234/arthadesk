<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Config;

Config::load();

$root = dirname(__DIR__);
$ok = true;

function line(string $label, string $value): void
{
    echo str_pad($label, 28) . $value . "\n";
}

function check(bool $condition, string $label, string $okValue, string $failValue): bool
{
    line($label, $condition ? "[OK] {$okValue}" : "[FAIL] {$failValue}");
    return $condition;
}

$appEnv = (string)Config::get('app.env', 'development');
$appDebug = (bool)Config::get('app.debug', true);
$appUrl = (string)Config::get('app.url', '');

line('Environment', $appEnv);
line('Debug', $appDebug ? 'enabled' : 'disabled');
line('URL', $appUrl);
line('PHP', PHP_VERSION . ' (' . PHP_SAPI . ')');
line('Timezone', date_default_timezone_get());
echo "\n";

foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'session', 'dom', 'gd'] as $extension) {
    $ok = check(extension_loaded($extension), "Extension {$extension}", 'loaded', 'missing') && $ok;
}

echo "\n";

foreach (['storage/logs', 'storage/cache', 'storage/uploads', 'storage/backups'] as $dir) {
    $path = $root . '/' . $dir;
    $ok = check(is_dir($path) && is_writable($path), $dir, 'writable', 'missing or not writable') && $ok;
}

echo "\n";

$dbConfig = Config::get('database.connections.mysql', []);
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['host'] ?? 'localhost',
    $dbConfig['port'] ?? '3306',
    $dbConfig['database'] ?? '',
    $dbConfig['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, (string)($dbConfig['username'] ?? ''), (string)($dbConfig['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    line('Database', '[OK] connected');
    line('MySQL version', (string)$pdo->query('SELECT VERSION()')->fetchColumn());

    $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    line('Tables', (string)$tables);

    $migrations = $pdo->query("SELECT migration, executed_at FROM migrations ORDER BY id DESC")->fetchAll();
    line('Migrations applied', (string)count($migrations));
    if ($migrations !== []) {
        line('Latest migration', (string)$migrations[0]['migration']);
    }

    $tenantCount = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
    line('Tenants', (string)$tenantCount);
} catch (Throwable $e) {
    line('Database', '[FAIL] ' . $e->getMessage());
    $ok = false;
}

echo "\n";
$diskFree = @disk_free_space($root);
if ($diskFree !== false) {
    line('Disk free', round($diskFree / 1073741824, 2) . ' GB');
}

echo "\n" . ($ok ? "Health check passed.\n" : "Health check failed.\n");
exit($ok ? 0 : 1);
