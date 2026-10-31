<?php
namespace App\Core;

use PDO;

class MigrationRunner {
    public static function run(bool $seedDemo = false): array {
        $pdo = Database::connect();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
        $executedMap = array_flip($executed);
        $results = [];

        $migDir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($migDir . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $base = basename($file);
            if (!isset($executedMap[$base])) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException("Unable to read migration: {$base}");
                }
                $pdo->exec($sql);
                $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)")->execute([$base]);
                $results[] = 'Migrated: ' . $base;
            }
        }

        if ($seedDemo) {
            $checkTenants = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
            if ($checkTenants === 0) {
                $seedDir = dirname(__DIR__, 2) . '/database/seeds';
                $seeded = false;
                foreach (glob($seedDir . '/*.sql') ?: [] as $seedFile) {
                    $sql = file_get_contents($seedFile);
                    if ($sql === false) {
                        continue;
                    }
                    $pdo->exec($sql);
                    $results[] = 'Seeded: ' . basename($seedFile);
                    $seeded = true;
                }

                if ($seeded) {
                    $plain = (string)env('DEMO_PASSWORD', 'password123');
                    if ($plain !== '') {
                        $hash = password_hash($plain, PASSWORD_BCRYPT);
                        $pdo->prepare("UPDATE users SET password_hash = ?")->execute([$hash]);
                        $results[] = 'Demo user passwords reset';
                    }
                }
            }
        }

        return $results;
    }
}
