<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Config;
use App\Core\Logger;

Config::load();

$root = dirname(__DIR__);
$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Could not create storage/backups.\n");
    exit(1);
}

$db = Config::get('database.connections.mysql', []);
$host = (string)($db['host'] ?? 'localhost');
$port = (string)($db['port'] ?? '3306');
$name = (string)($db['database'] ?? '');
$user = (string)($db['username'] ?? '');
$pass = (string)($db['password'] ?? '');

if ($name === '' || $user === '') {
    fwrite(STDERR, "Database configuration is incomplete.\n");
    exit(1);
}

$mysqldump = null;
foreach (['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/homebrew/bin/mysqldump'] as $candidate) {
    $probe = escapeshellarg($candidate) . ' --version 2>&1';
    exec($probe, $probeOut, $probeCode);
    if ($probeCode === 0) {
        $mysqldump = $candidate;
        break;
    }
}

if ($mysqldump === null) {
    fwrite(STDERR, "mysqldump was not found. Install MySQL client tools or use Hostinger's database backup.\n");
    exit(1);
}

$timestamp = gmdate('Ymd-His');
$file = $backupDir . '/db-' . $timestamp . '.sql';
$command = [
    $mysqldump,
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $user,
    '--single-transaction',
    '--quick',
    '--no-tablespaces',
    '--default-character-set=utf8mb4',
    $name,
];

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $file, 'wb'],
    2 => ['pipe', 'w'],
];

$env = getenv();
$env['MYSQL_PWD'] = $pass;

$process = proc_open($command, $descriptors, $pipes, $root, $env);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start mysqldump.\n");
    exit(1);
}

fclose($pipes[0]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0 || !is_file($file) || filesize($file) === 0) {
    @unlink($file);
    Logger::error('backup_failed', ['exit_code' => $exitCode, 'error' => trim((string)$stderr)], 'backup');
    fwrite(STDERR, "Backup failed (exit {$exitCode}).\n");
    if (trim((string)$stderr) !== '') {
        fwrite(STDERR, trim((string)$stderr) . "\n");
    }
    exit(1);
}

@chmod($file, 0600);
$sizeMb = round(filesize($file) / 1048576, 2);
Logger::info('backup_completed', ['file' => basename($file), 'size_mb' => $sizeMb], 'backup');

$retentionDays = 14;
foreach (glob($backupDir . '/db-*.sql') ?: [] as $oldFile) {
    if ((time() - (int)filemtime($oldFile)) / 86400 > $retentionDays && unlink($oldFile)) {
        Logger::info('backup_pruned', ['file' => basename($oldFile)], 'backup');
    }
}

echo "Backup created: storage/backups/" . basename($file) . " ({$sizeMb} MB)\n";
exit(0);
