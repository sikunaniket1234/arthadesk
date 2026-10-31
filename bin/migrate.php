<?php
require_once dirname(__DIR__) . '/app/autoload.php';

use App\Core\Config;
use App\Core\MigrationRunner;

Config::load();
$pdo = \App\Core\Database::connect();
echo "Executed migrations in DB:\n";
print_r($pdo->query("SELECT * FROM migrations")->fetchAll(\PDO::FETCH_ASSOC));

$results = MigrationRunner::run();
echo "Migration results:\n";
print_r($results);
