<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Audit trail service recording state changes on accounts, patients, and medical records.
 */
final class AuditService
{
    private const REDACTED_FIELDS = [
        'password',
        'password_hash',
        'pin',
        'pin_hash',
        'validator_hash',
        'token_hash',
    ];

    /**
     * Record an audit log entry in the database.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public static function record(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $patientId = 1,
        ?int $actorId = null
    ): void {
        $userId = $actorId ?? Auth::id();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 255);

        $beforeJson = $before !== null
            ? json_encode(self::sanitize($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $afterJson = $after !== null
            ? json_encode(self::sanitize($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO `audit_logs`
             (`user_id`, `patient_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `ip_address`, `user_agent`, `created_at`)
             VALUES (:user_id, :patient_id, :action, :entity_type, :entity_id, :before_data, :after_data, :ip, :ua, :created_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $beforeJson,
            'after_data' => $afterJson,
            'ip' => $ip,
            'ua' => $userAgent,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Alias for record() accepting actorId as 6th parameter.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
        int $patientId = 1
    ): void {
        self::record($action, $entityType, $entityId, $before, $after, $patientId, $actorId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::REDACTED_FIELDS, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
