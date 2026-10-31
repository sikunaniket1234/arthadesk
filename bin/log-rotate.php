<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$logDir = $root . '/storage/logs';
$compressAfterDays = 7;
$retentionDays = 90;

$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^LOG_RETENTION_DAYS\s*=\s*(\d+)/', trim($line), $matches)) {
            $retentionDays = max(1, (int)$matches[1]);
        }
    }
}

if (!is_dir($logDir)) {
    echo "Log directory does not exist: storage/logs\n";
    exit(0);
}

$now = time();
$compressed = 0;
$deleted = 0;
$skipped = 0;

foreach (glob($logDir . '/*.log') ?: [] as $file) {
    $ageDays = ($now - (int)filemtime($file)) / 86400;
    if ($ageDays < $compressAfterDays) {
        $skipped++;
        continue;
    }

    $source = fopen($file, 'rb');
    if ($source === false) {
        fwrite(STDERR, "Cannot read {$file}\n");
        continue;
    }
    $targetFile = $file . '.gz';
    $target = gzopen($targetFile, 'wb9');
    if ($target === false) {
        fclose($source);
        fwrite(STDERR, "Cannot write {$targetFile}\n");
        continue;
    }

    while (!feof($source)) {
        gzwrite($target, (string)fread($source, 1048576));
    }
    fclose($source);
    gzclose($target);

    if (unlink($file)) {
        $compressed++;
    } else {
        fwrite(STDERR, "Compressed but could not remove {$file}\n");
    }
}

foreach (glob($logDir . '/*.gz') ?: [] as $file) {
    $ageDays = ($now - (int)filemtime($file)) / 86400;
    if ($ageDays > $retentionDays && unlink($file)) {
        $deleted++;
    }
}

echo "Compressed: {$compressed}\n";
echo "Deleted: {$deleted}\n";
echo "Skipped: {$skipped}\n";
exit(0);
