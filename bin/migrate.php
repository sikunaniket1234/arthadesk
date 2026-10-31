<?php
require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Config;
use App\Core\MigrationRunner;

Config::load();

$dbName = (string)Config::get('database.connections.mysql.database', '');
echo "Database: {$dbName}" . PHP_EOL;

try {
    $pdo = \App\Core\Database::connect();

    // On a fresh database the migrations table does not exist yet — do not query it first.
    $hasTable = $pdo->query("SHOW TABLES LIKE 'migrations'")->fetch();

    if ($hasTable) {
        $applied = $pdo->query("SELECT migration, executed_at FROM migrations ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        echo 'Already applied: ' . count($applied) . PHP_EOL;
        foreach ($applied as $row) {
            echo '  - ' . $row['migration'] . ' (' . $row['executed_at'] . ')' . PHP_EOL;
        }
    } else {
        echo "Fresh database: migrations table not created yet." . PHP_EOL;
    }

    echo PHP_EOL . "Applying migrations..." . PHP_EOL;
    $results = MigrationRunner::run();

    if (is_array($results) && $results !== []) {
        foreach ($results as $result) {
            echo '  ' . $result . PHP_EOL;
        }
    } else {
        echo "  (already up to date)" . PHP_EOL;
    }

    $total = (int)$pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo PHP_EOL . "Migrations recorded: {$total}" . PHP_EOL;
    echo "Tables created: {$tables}" . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
