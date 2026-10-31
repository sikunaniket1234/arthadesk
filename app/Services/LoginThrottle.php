<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

class LoginThrottle {
    public static function enabled(): bool {
        return (bool)Config::get('security.login_throttle.enabled', true);
    }

    public static function tooMany(string $email, string $ip): bool {
        if (!self::enabled()) {
            return false;
        }

        try {
            $minutes = max(1, (int)Config::get('security.login_throttle.window_minutes', 15));
            $cutoff = date('Y-m-d H:i:s', time() - ($minutes * 60));

            $max = max(1, (int)Config::get('security.login_throttle.max_attempts', 5));
            $row = Database::fetchOne("
                SELECT COUNT(*) AS c
                FROM login_attempts
                WHERE success = 0
                  AND created_at >= :cutoff
                  AND email = :email
                  AND ip = :ip
            ", ['cutoff' => $cutoff, 'email' => strtolower(trim($email)), 'ip' => $ip]);

            if ((int)($row['c'] ?? 0) >= $max) {
                return true;
            }

            $ipMax = max(1, (int)Config::get('security.login_throttle.ip_max_attempts', 20));
            $ipRow = Database::fetchOne("
                SELECT COUNT(*) AS c
                FROM login_attempts
                WHERE success = 0
                  AND created_at >= :cutoff
                  AND ip = :ip
            ", ['cutoff' => $cutoff, 'ip' => $ip]);

            return (int)($ipRow['c'] ?? 0) >= $ipMax;
        } catch (\Throwable $e) {
            Logger::error('login_throttle_check_failed', ['error' => $e->getMessage()], 'security');
            return false;
        }
    }

    public static function record(string $email, string $ip, bool $success, string $reason = '', ?int $tenantId = null): void {
        if (!self::enabled()) {
            return;
        }

        try {
            $stmt = Database::connect()->prepare("
                INSERT INTO login_attempts (tenant_id, email, ip, success, reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                substr(strtolower(trim($email)), 0, 150),
                substr($ip, 0, 45),
                $success ? 1 : 0,
                substr($reason, 0, 100),
            ]);
        } catch (\Throwable $e) {
            Logger::error('login_throttle_record_failed', ['error' => $e->getMessage()], 'security');
        }
    }
}
