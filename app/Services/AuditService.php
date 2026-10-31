<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;

class AuditService {
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        ?int $tenantId = null,
        ?int $userId = null
    ): void {
        $user = Session::get('user');
        $userId = $userId ?? (int)($user['id'] ?? 0);
        $tenantId = $tenantId ?? (int)($user['tenant_id'] ?? Session::get('current_tenant_id') ?? 0);

        $payload = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => Logger::sanitize($details),
            'request_id' => Logger::requestId(),
            'ip' => self::ip(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ];

        try {
            $stmt = Database::connect()->prepare("
                INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, details, ip_address, created_at)
                VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId > 0 ? $tenantId : null,
                $userId,
                substr($action, 0, 100),
                $entityType ? substr($entityType, 0, 50) : null,
                $entityId,
                json_encode($payload['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $payload['ip'],
            ]);
        } catch (\Throwable $e) {
            Logger::warning('audit_insert_failed', ['action' => $action, 'error' => $e->getMessage()], 'audit');
        }

        Logger::info($action, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $payload['details'],
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'ip' => $payload['ip'],
        ], 'audit');
    }

    public static function ip(): string {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($forwarded)) {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
