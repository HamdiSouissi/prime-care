<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Generates automatic shift handover summaries (since last handover or over the last 12 hours),
 * detects unread handovers for the incoming team, and tracks read receipts per user.
 */
final class HandoverService
{
    /**
     * Build the automatic shift summary since the last handover (or at most the last 12 hours).
     *
     * @return array{
     *   from_utc: string,
     *   to_utc: string,
     *   from_local: string,
     *   to_local: string,
     *   measures_total: int,
     *   measures_out_of_target: int,
     *   measures_critical: int,
     *   last_glycemia: array<string, mixed>|null,
     *   intakes_given: int,
     *   intakes_skipped: int,
     *   meals_count: int,
     *   avg_portion_pct: int,
     *   care_checks_count: int,
     *   important_notes_count: int,
     *   summary_text: string
     * }
     */
    public static function buildShiftSummary(?string $referenceUtc = null, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $nowDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $twelveHoursAgoUtc = $nowDt->modify('-12 hours')->format('Y-m-d H:i:s');
        $nowUtc = $nowDt->format('Y-m-d H:i:s');

        // Check if there was a handover within the last 12h
        $lastStmt = $pdo->prepare(
            'SELECT `created_at`
             FROM `notes`
             WHERE `patient_id` = :pid
               AND `is_handover` = 1
               AND `deleted_at` IS NULL
               AND `created_at` < :now_utc
             ORDER BY `created_at` DESC
             LIMIT 1'
        );
        $lastStmt->execute(['pid' => $patientId, 'now_utc' => $nowUtc]);
        $lastRow = $lastStmt->fetch(PDO::FETCH_ASSOC);

        $fromUtc = $twelveHoursAgoUtc;
        if ($lastRow && (string) $lastRow['created_at'] > $twelveHoursAgoUtc) {
            $fromUtc = (string) $lastRow['created_at'];
        }

        // 1. Measures count & out-of-target
        $mStmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `alert_level` IN (\'warn\', \'critical\') THEN 1 ELSE 0 END) AS out_of_target,
                SUM(CASE WHEN `alert_level` = \'critical\' THEN 1 ELSE 0 END) AS critical_count
             FROM `measures`
             WHERE `patient_id` = :pid
               AND `measured_at` BETWEEN :from_utc AND :to_utc
               AND `deleted_at` IS NULL'
        );
        $mStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $nowUtc]);
        $mRow = $mStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $measuresTotal = (int) ($mRow['total'] ?? 0);
        $measuresOut = (int) ($mRow['out_of_target'] ?? 0);
        $measuresCrit = (int) ($mRow['critical_count'] ?? 0);

        // Last glycemia
        $gStmt = $pdo->prepare(
            'SELECT m.`value`, m.`context`, m.`alert_level`, m.`measured_at`
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND mt.code = \'glycemia\'
               AND m.deleted_at IS NULL
             ORDER BY m.measured_at DESC
             LIMIT 1'
        );
        $gStmt->execute(['pid' => $patientId]);
        $lastGlycemia = $gStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2. Intakes given & skipped
        $iStmt = $pdo->prepare(
            'SELECT
                SUM(CASE WHEN `status` = \'given\' THEN 1 ELSE 0 END) AS given_cnt,
                SUM(CASE WHEN `status` = \'skipped\' THEN 1 ELSE 0 END) AS skipped_cnt
             FROM `medication_intakes`
             WHERE `patient_id` = :pid
               AND `given_at` BETWEEN :from_utc AND :to_utc
               AND `deleted_at` IS NULL'
        );
        $iStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $nowUtc]);
        $iRow = $iStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $intakesGiven = (int) ($iRow['given_cnt'] ?? 0);
        $intakesSkipped = (int) ($iRow['skipped_cnt'] ?? 0);

        // 3. Meals
        $mealStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt, AVG(`portion_eaten`) AS avg_portion
             FROM `meals`
             WHERE `patient_id` = :pid
               AND `eaten_at` BETWEEN :from_utc AND :to_utc
               AND `deleted_at` IS NULL'
        );
        $mealStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $nowUtc]);
        $mealRow = $mealStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $mealsCount = (int) ($mealRow['cnt'] ?? 0);
        $avgPortion = $mealsCount > 0 ? (int) round((float) ($mealRow['avg_portion'] ?? 100)) : 100;

        // 4. Care checks & important notes
        $cStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `care_checks`
             WHERE `patient_id` = :pid AND `checked_at` BETWEEN :from_utc AND :to_utc AND `deleted_at` IS NULL'
        );
        $cStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $nowUtc]);
        $careChecksCount = (int) $cStmt->fetchColumn();

        $nStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `notes`
             WHERE `patient_id` = :pid
               AND `priority` IN (\'important\', \'urgent\')
               AND `is_handover` = 0
               AND `created_at` BETWEEN :from_utc AND :to_utc
               AND `deleted_at` IS NULL'
        );
        $nStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $nowUtc]);
        $importantNotesCount = (int) $nStmt->fetchColumn();

        $fromLocal = ScheduleService::utcToLocalParis($fromUtc, 'H:i');
        $toLocal = ScheduleService::utcToLocalParis($nowUtc, 'H:i');

        $parts = [
            sprintf('%d mesure(s) dont %d hors cible', $measuresTotal, $measuresOut),
            sprintf('%d prise(s) donnée(s)%s', $intakesGiven, $intakesSkipped > 0 ? ", {$intakesSkipped} non donnée(s)" : ''),
            sprintf('%d repas (portion moy. %d %%)', $mealsCount, $avgPortion),
            sprintf('%d soin(s) réalisé(s)', $careChecksCount),
        ];
        if ($importantNotesCount > 0) {
            $parts[] = sprintf('%d note(s) importante(s)', $importantNotesCount);
        }

        return [
            'from_utc' => $fromUtc,
            'to_utc' => $nowUtc,
            'from_local' => $fromLocal,
            'to_local' => $toLocal,
            'measures_total' => $measuresTotal,
            'measures_out_of_target' => $measuresOut,
            'measures_critical' => $measuresCrit,
            'last_glycemia' => $lastGlycemia,
            'intakes_given' => $intakesGiven,
            'intakes_skipped' => $intakesSkipped,
            'meals_count' => $mealsCount,
            'avg_portion_pct' => $avgPortion,
            'care_checks_count' => $careChecksCount,
            'important_notes_count' => $importantNotesCount,
            'summary_text' => implode(' · ', $parts),
        ];
    }

    /**
     * Get the latest unread handover for the given user (excluding handovers authored by the user).
     *
     * @return array<string, mixed>|null
     */
    public static function getLatestUnreadHandover(int $userId, int $patientId = 1): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT n.*,
                    u.first_name AS author_first_name,
                    u.last_name AS author_last_name,
                    u.role AS author_role
             FROM `notes` n
             INNER JOIN `users` u ON u.id = n.recorded_by
             LEFT JOIN `note_reads` nr ON nr.note_id = n.id AND nr.user_id = :user_id
             WHERE n.patient_id = :pid
               AND n.is_handover = 1
               AND n.deleted_at IS NULL
               AND n.recorded_by != :author_exclude
               AND nr.id IS NULL
             ORDER BY n.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'pid' => $patientId,
            'author_exclude' => $userId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['created_at_local'] = ScheduleService::utcToLocalParis((string) $row['created_at'], 'd/m à H:i');
        $row['summary_decoded'] = is_string($row['handover_summary'] ?? null)
            ? (json_decode((string) $row['handover_summary'], true) ?: [])
            : [];

        return $row;
    }

    /**
     * Mark a note/handover as read by the given user (idempotent via UNIQUE(note_id, user_id)).
     */
    public static function markAsRead(int $noteId, int $userId, int $patientId = 1): bool
    {
        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO `note_reads`
             (`patient_id`, `note_id`, `user_id`, `read_at`, `created_at`, `updated_at`, `created_by`)
             VALUES (:pid, :note_id, :user_id, :read_at, :created_at, :updated_at, :created_by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'note_id' => $noteId,
            'user_id' => $userId,
            'read_at' => $nowUtc,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $userId,
        ]);

        AuditService::record(
            'note.read',
            'note',
            $noteId,
            null,
            ['user_id' => $userId, 'read_at' => $nowUtc],
            $patientId,
            $userId
        );

        return true;
    }
}
