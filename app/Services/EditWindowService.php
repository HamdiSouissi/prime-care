<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Enforces the 30-minute modification rule (Cahier des charges §2):
 * - A caregiver can modify their own entry during the first 30 minutes after creation.
 * - After 30 minutes (or for another user's entry), only 'admin' and 'coordinator' can modify it,
 *   and a modification reason (modified_reason) is mandatory.
 */
final class EditWindowService
{
    public const WINDOW_MINUTES = 30;

    /**
     * Check whether the given user is allowed to modify a medical record and whether a reason is required.
     *
     * @param array<string, mixed> $record
     * @return array{
     *   allowed: bool,
     *   requires_reason: bool,
     *   http_status: int,
     *   code: string|null,
     *   message: string|null
     * }
     */
    public static function checkEditPermission(
        array $record,
        int $userId,
        string $userRole,
        ?string $modifiedReason = null,
        string|DateTimeImmutable|null $nowUtc = null
    ): array {
        if ($userRole === 'viewer') {
            return [
                'allowed' => false,
                'requires_reason' => false,
                'http_status' => 403,
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'Votre rôle en consultation seule ne permet pas de modifier cet enregistrement.',
            ];
        }

        $ownerId = (int) ($record['recorded_by'] ?? $record['given_by'] ?? $record['performed_by'] ?? $record['created_by'] ?? 0);
        $createdAtStr = (string) ($record['created_at'] ?? gmdate('Y-m-d H:i:s'));

        $utcTz = new DateTimeZone('UTC');
        $createdDt = new DateTimeImmutable($createdAtStr, $utcTz);
        if ($nowUtc instanceof DateTimeImmutable) {
            $nowDt = $nowUtc->setTimezone($utcTz);
        } elseif (is_string($nowUtc) && $nowUtc !== '') {
            $nowDt = new DateTimeImmutable($nowUtc, $utcTz);
        } else {
            $nowDt = new DateTimeImmutable('now', $utcTz);
        }

        $elapsedSeconds = max(0, $nowDt->getTimestamp() - $createdDt->getTimestamp());
        $within30Min = $elapsedSeconds <= (self::WINDOW_MINUTES * 60);
        $isOwnRecord = $ownerId > 0 && $ownerId === $userId;

        if ($userRole === 'caregiver') {
            if (!$isOwnRecord) {
                return [
                    'allowed' => false,
                    'requires_reason' => false,
                    'http_status' => 403,
                    'code' => 'NOT_OWNER',
                    'message' => 'Une aide-soignante ne peut modifier que ses propres saisies.',
                ];
            }

            if (!$within30Min) {
                return [
                    'allowed' => false,
                    'requires_reason' => true,
                    'http_status' => 403,
                    'code' => 'EDIT_WINDOW_EXPIRED',
                    'message' => 'Le délai de modification de 30 minutes est dépassé. Seul le responsable ou la référente peut modifier cette saisie avec un motif.',
                ];
            }

            return [
                'allowed' => true,
                'requires_reason' => false,
                'http_status' => 200,
                'code' => null,
                'message' => null,
            ];
        }

        // For admin and coordinator: if > 30 min or modifying another user's entry, reason is mandatory
        $requiresReason = !$within30Min || !$isOwnRecord;
        if ($requiresReason && trim((string) $modifiedReason) === '') {
            return [
                'allowed' => false,
                'requires_reason' => true,
                'http_status' => 422,
                'code' => 'REASON_REQUIRED',
                'message' => 'Un motif de modification est obligatoire au-delà de 30 minutes ou sur la saisie d’un tiers.',
            ];
        }

        return [
            'allowed' => true,
            'requires_reason' => $requiresReason,
            'http_status' => 200,
            'code' => null,
            'message' => null,
        ];
    }
}
