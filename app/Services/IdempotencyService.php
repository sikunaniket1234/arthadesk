<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class IdempotencyService {
    public static function begin(int $tenantId, ?int $userId, string $key, string $requestHash, int $staleSeconds = 300): array {
        $key = trim($key);
        if ($key === '') {
            return ['state' => 'new', 'response' => null];
        }

        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return ['state' => 'invalid', 'response' => null];
        }

        try {
            $existing = Database::fetchOne(
                "SELECT * FROM idempotency_keys WHERE tenant_id = :tid AND idem_key = :k LIMIT 1",
                ['tid' => $tenantId, 'k' => $key]
            );

            if ($existing) {
                if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                    return ['state' => 'conflict', 'response' => null];
                }

                if ($existing['status'] === 'completed') {
                    return [
                        'state' => 'completed',
                        'response' => json_decode((string)$existing['response_json'], true),
                    ];
                }

                $age = time() - strtotime((string)$existing['updated_at']);
                if ($existing['status'] === 'processing' && $age < $staleSeconds) {
                    return ['state' => 'processing', 'response' => null];
                }

                Database::query(
                    "UPDATE idempotency_keys SET status = 'processing', request_hash = :h, updated_at = NOW() WHERE id = :id",
                    ['h' => $requestHash, 'id' => $existing['id']]
                );
                return ['state' => 'new', 'response' => null];
            }

            Database::query(
                "INSERT INTO idempotency_keys (tenant_id, user_id, idem_key, request_hash, status, created_at, updated_at)
                 VALUES (:tid, :uid, :k, :h, 'processing', NOW(), NOW())",
                ['tid' => $tenantId, 'uid' => $userId, 'k' => $key, 'h' => $requestHash]
            );

            return ['state' => 'new', 'response' => null];
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $existing = Database::fetchOne(
                    "SELECT * FROM idempotency_keys WHERE tenant_id = :tid AND idem_key = :k LIMIT 1",
                    ['tid' => $tenantId, 'k' => $key]
                );
                if ($existing && $existing['status'] === 'completed') {
                    return [
                        'state' => 'completed',
                        'response' => json_decode((string)$existing['response_json'], true),
                    ];
                }
                return ['state' => 'processing', 'response' => null];
            }

            Logger::error('idempotency_begin_failed', ['error' => $e->getMessage()], 'api');
            return ['state' => 'new', 'response' => null];
        }
    }

    public static function complete(int $tenantId, string $key, array $response): void {
        if ($key === '') {
            return;
        }
        try {
            Database::query(
                "UPDATE idempotency_keys SET status = 'completed', response_json = :r, updated_at = NOW()
                 WHERE tenant_id = :tid AND idem_key = :k",
                [
                    'r' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'tid' => $tenantId,
                    'k' => $key,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('idempotency_complete_failed', ['error' => $e->getMessage()], 'api');
        }
    }

    public static function fail(int $tenantId, string $key, string $reason): void {
        if ($key === '') {
            return;
        }
        try {
            Database::query(
                "UPDATE idempotency_keys SET status = 'failed', response_json = :r, updated_at = NOW()
                 WHERE tenant_id = :tid AND idem_key = :k",
                [
                    'r' => json_encode(['error' => $reason], JSON_UNESCAPED_UNICODE),
                    'tid' => $tenantId,
                    'k' => $key,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('idempotency_fail_failed', ['error' => $e->getMessage()], 'api');
        }
    }
}
