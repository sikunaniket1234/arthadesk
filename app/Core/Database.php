<?php
namespace App\Core;

use PDO;
use Exception;

class Database {
    protected static ?PDO $pdo = null;

    public static function connect(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        Config::load();
        $dbConfig = Config::get('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
            self::$pdo->exec("SET NAMES " . $dbConfig['charset']);
        } catch (Exception $e) {
            if (Config::get('app.debug', false)) {
                die("Database Connection Error: " . $e->getMessage());
            } else {
                error_log("DB Connection Error: " . $e->getMessage());
                die("Database service currently unavailable. Please try again later.");
            }
        }

        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $res = self::query($sql, $params)->fetch();
        return $res ?: null;
    }

    public static function lastInsertId(): string {
        return self::connect()->lastInsertId();
    }

    public static function beginTransaction(): bool {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool {
        return self::connect()->commit();
    }

    public static function rollBack(): bool {
        if (self::connect()->inTransaction()) {
            return self::connect()->rollBack();
        }
        return false;
    }

    public static function transaction(callable $callback) {
        self::beginTransaction();
        try {
            $result = $callback(self::connect());
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
