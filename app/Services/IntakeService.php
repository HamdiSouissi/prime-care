<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Manages medication intake status calculation, atomic 'Given' / 'Skipped' validation
 * with SELECT ... FOR UPDATE + optimistic version locking (preventing double dosing with HTTP 409),
 * 'as-needed' minimum interval enforcement, and 7d/30d observance metrics.
 */
final class IntakeService
{
    public const SKIP_REASONS = [
        'refus' => 'Refus du patient',
        'endormi' => 'Patient profondément endormi',
        'vomissement' => 'Nausées / vomissements',
        'hypoglycemie' => 'Hypoglycémie (< 0,70 g/L)',
        'rupture_stock' => 'Rupture de stock au domicile',
        'avis_medical' => 'Suspension sur avis médical',
        'autre' => 'Autre motif (préciser)',
    ];

    /**
     * Get the configured intake window in minutes (default: 30).
     */
    public static function getWindowMinutes(int $patientId = 1): int
    {
        try {
            $cfg = Setting::getValue('intake_window_min', ['minutes' => 30], $patientId);
            if (is_array($cfg) && isset($cfg['minutes'])) {
                return max(5, min(180, (int) $cfg['minutes']));
            }
            if (is_numeric($cfg)) {
                return max(5, min(180, (int) $cfg));
            }
        } catch (\Throwable) {
            // Fallback to default 30 min
        }
        return 30;
    }

    /**
     * Compute the display status of an intake: 'upcoming', 'due', 'late', 'given', 'skipped'.
     * Always provides an accessible icon + French label (never color alone).
     *
     * @param array<string, mixed> $intake
     * @return array{
     *   display_status: string,
     *   label: string,
     *   icon: string,
     *   badge_class: string,
     *   is_actionable: bool
     * }
     */
    public static function computeDisplayStatus(
        array $intake,
        ?string $referenceUtc = null,
        ?int $windowMinutes = null
    ): array {
        $dbStatus = (string) ($intake['status'] ?? 'pending');
        if ($dbStatus === 'given') {
            return [
                'display_status' => 'given',
                'label' => 'Donné',
                'icon' => 'check-circle',
                'badge_class' => 'pc-status-pill--ok',
                'is_actionable' => false,
            ];
        }

        if ($dbStatus === 'skipped') {
            return [
                'display_status' => 'skipped',
                'label' => 'Non donné',
                'icon' => 'x-circle',
                'badge_class' => 'pc-status-pill--warn',
                'is_actionable' => false,
            ];
        }

        $windowMin = $windowMinutes ?? self::getWindowMinutes((int) ($intake['patient_id'] ?? 1));
        $utcTz = new DateTimeZone('UTC');
        $nowDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);
        $schedDt = new DateTimeImmutable((string) $intake['scheduled_at'], $utcTz);

        $diffSeconds = $nowDt->getTimestamp() - $schedDt->getTimestamp();
        $windowSeconds = $windowMin * 60;

        if ($diffSeconds < -$windowSeconds) {
            return [
                'display_status' => 'upcoming',
                'label' => 'À venir',
                'icon' => 'clock',
                'badge_class' => 'pc-status-pill--neutral',
                'is_actionable' => true,
            ];
        }

        if ($diffSeconds <= $windowSeconds) {
            return [
                'display_status' => 'due',
                'label' => 'À donner',
                'icon' => 'bell',
                'badge_class' => 'pc-status-pill--info',
                'is_actionable' => true,
            ];
        }

        return [
            'display_status' => 'late',
            'label' => 'En retard',
            'icon' => 'alert-triangle',
            'badge_class' => 'pc-status-pill--critical',
            'is_actionable' => true,
        ];
    }

    /**
     * Validate an intake as 'given'. Uses a database transaction + SELECT ... FOR UPDATE + optimistic
     * version check so concurrent double-dosing is impossible (returns HTTP 409 with a clear message).
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, http_status: int, code?: string, message: string, data?: array<string, mixed>}
     */
    public static function giveIntake(
        int $intakeId,
        int $userId,
        array $payload = [],
        ?string $nowUtc = null
    ): array {
        $effectiveNowUtc = $nowUtc ?? gmdate('Y-m-d H:i:s');

        return Database::transaction(static function (PDO $pdo) use ($intakeId, $userId, $payload, $effectiveNowUtc): array {
            $stmt = $pdo->prepare(
                'SELECT mi.*,
                        p.dose AS rx_dose,
                        p.unit AS rx_unit,
                        p.insulin_kind,
                        p.dose_mode,
                        p.correction_table,
                        pt.dose_override,
                        m.name AS medication_name,
                        u.first_name AS giver_first_name,
                        u.last_name AS giver_last_name
                 FROM `medication_intakes` mi
                 INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
                 INNER JOIN `medications` m ON m.id = p.medication_id
                 LEFT JOIN `prescription_times` pt ON pt.id = mi.prescription_time_id
                 LEFT JOIN `users` u ON u.id = mi.given_by
                 WHERE mi.id = :id AND mi.deleted_at IS NULL
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $intakeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'code' => 'INTAKE_NOT_FOUND',
                    'message' => 'Prise de médicament introuvable.',
                ];
            }

            $expectedVersion = isset($payload['version']) && $payload['version'] !== ''
                ? (int) $payload['version']
                : null;

            if ($row['status'] !== 'pending' || ($expectedVersion !== null && (int) $row['version'] !== $expectedVersion)) {
                $giverName = trim((string) ($row['giver_first_name'] ?? 'un membre de l’équipe'));
                $whenUtc = (string) ($row['given_at'] ?? $row['updated_at'] ?? $effectiveNowUtc);
                $whenParis = str_replace(':', 'h', ScheduleService::utcToLocalParis($whenUtc, 'H:i'));

                $conflictMsg = $row['status'] === 'given'
                    ? sprintf('Déjà donné par %s à %s', $giverName, $whenParis)
                    : sprintf('Déjà marqué comme non donné par %s à %s', $giverName, $whenParis);

                return [
                    'ok' => false,
                    'http_status' => 409,
                    'code' => 'ALREADY_GIVEN',
                    'message' => $conflictMsg,
                ];
            }

            $defaultDose = $row['dose_override'] !== null
                ? (float) $row['dose_override']
                : (float) $row['rx_dose'];

            $relatedMeasureId = isset($payload['related_measure_id']) && (int) $payload['related_measure_id'] > 0
                ? (int) $payload['related_measure_id']
                : null;

            $proposedDose = $defaultDose;

            // Variable insulin requires an associated glycemia measure
            if ((string) $row['dose_mode'] === 'tableau') {
                $glycemiaVal = null;

                if ($relatedMeasureId !== null) {
                    $mStmt = $pdo->prepare('SELECT `value` FROM `measures` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
                    $mStmt->execute(['id' => $relatedMeasureId]);
                    $mRow = $mStmt->fetch(PDO::FETCH_ASSOC);
                    if ($mRow) {
                        $glycemiaVal = (float) $mRow['value'];
                    }
                } elseif (isset($payload['glycemia_value']) && is_numeric($payload['glycemia_value'])) {
                    $glycemiaVal = (float) $payload['glycemia_value'];
                    // Record the associated glycemia measurement automatically
                    $gtStmt = $pdo->prepare('SELECT * FROM `measure_types` WHERE `patient_id` = :pid AND `code` = \'glycemia\' LIMIT 1');
                    $gtStmt->execute(['pid' => (int) $row['patient_id']]);
                    $gtRow = $gtStmt->fetch(PDO::FETCH_ASSOC);
                    if ($gtRow) {
                        $eval = ThresholdService::evaluate($gtRow, $glycemiaVal, 'pre_meal');
                        $insM = $pdo->prepare(
                            'INSERT INTO `measures`
                             (`patient_id`, `measure_type_id`, `value`, `unit_recorded`, `context`, `measured_at`, `alert_level`, `threshold_level`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
                             VALUES (:pid, :tid, :val, \'g/L\', \'pre_meal\', :at, :alert, :tlevel, :by, :created_at, :updated_at, :created_by)'
                        );
                        $insM->execute([
                            'pid' => (int) $row['patient_id'],
                            'tid' => (int) $gtRow['id'],
                            'val' => $glycemiaVal,
                            'at' => $effectiveNowUtc,
                            'alert' => $eval['alert_level'],
                            'tlevel' => $eval['threshold_level'],
                            'by' => $userId,
                            'created_at' => $effectiveNowUtc,
                            'updated_at' => $effectiveNowUtc,
                            'created_by' => $userId,
                        ]);
                        $relatedMeasureId = (int) $pdo->lastInsertId();
                        ScheduleService::fulfillExpectationWithMeasure(
                            $relatedMeasureId,
                            (int) $gtRow['id'],
                            $effectiveNowUtc,
                            (int) $row['patient_id']
                        );
                    }
                }

                if ($glycemiaVal === null || $glycemiaVal <= 0.0) {
                    return [
                        'ok' => false,
                        'http_status' => 422,
                        'code' => 'GLYCEMIA_REQUIRED',
                        'message' => 'Une glycémie associée est obligatoire pour valider cette prise d’insuline à dose variable.',
                    ];
                }

                $table = is_string($row['correction_table'])
                    ? (json_decode($row['correction_table'], true) ?: [])
                    : [];
                $lookup = InsulinService::lookupDose($table, $glycemiaVal);
                if ($lookup['proposed_dose'] !== null) {
                    $proposedDose = (float) $lookup['proposed_dose'];
                }
            }

            $givenDose = isset($payload['given_dose']) && $payload['given_dose'] !== '' && is_numeric($payload['given_dose'])
                ? (float) $payload['given_dose']
                : $proposedDose;

            $injectionSite = isset($payload['injection_site']) && trim((string) $payload['injection_site']) !== ''
                ? trim((string) $payload['injection_site'])
                : null;
            $comment = isset($payload['comment']) && trim((string) $payload['comment']) !== ''
                ? trim((string) $payload['comment'])
                : null;

            $currentVersion = (int) $row['version'];
            $newVersion = $currentVersion + 1;

            $upd = $pdo->prepare(
                'UPDATE `medication_intakes`
                 SET `status` = \'given\',
                     `given_at` = :given_at,
                     `given_dose` = :given_dose,
                     `injection_site` = :injection_site,
                     `related_measure_id` = :related_measure_id,
                     `comment` = :comment,
                     `given_by` = :given_by,
                     `version` = :new_version,
                     `updated_at` = :updated_at
                 WHERE `id` = :id
                   AND `status` = \'pending\'
                   AND `version` = :current_version'
            );
            $upd->execute([
                'given_at' => $effectiveNowUtc,
                'given_dose' => $givenDose,
                'injection_site' => $injectionSite,
                'related_measure_id' => $relatedMeasureId,
                'comment' => $comment,
                'given_by' => $userId,
                'new_version' => $newVersion,
                'updated_at' => $effectiveNowUtc,
                'id' => $intakeId,
                'current_version' => $currentVersion,
            ]);

            if ($upd->rowCount() === 0) {
                return [
                    'ok' => false,
                    'http_status' => 409,
                    'code' => 'ALREADY_GIVEN',
                    'message' => 'Cette prise vient d’être validée simultanément par un autre membre de l’équipe.',
                ];
            }

            $afterData = [
                'status' => 'given',
                'given_at' => $effectiveNowUtc,
                'given_dose' => $givenDose,
                'injection_site' => $injectionSite,
                'related_measure_id' => $relatedMeasureId,
                'given_by' => $userId,
                'version' => $newVersion,
            ];

            AuditService::record(
                'intake.give',
                'medication_intake',
                $intakeId,
                ['status' => $row['status'], 'version' => $currentVersion],
                $afterData,
                (int) $row['patient_id'],
                $userId
            );

            return [
                'ok' => true,
                'http_status' => 200,
                'message' => sprintf(
                    'Prise de %s (%s %s) enregistrée.',
                    (string) $row['medication_name'],
                    str_replace('.', ',', (string) $givenDose),
                    (string) $row['rx_unit']
                ),
                'data' => array_merge($afterData, [
                    'id' => $intakeId,
                    'medication_name' => $row['medication_name'],
                    'unit' => $row['rx_unit'],
                    'given_at_local' => ScheduleService::utcToLocalParis($effectiveNowUtc, 'H:i'),
                ]),
            ];
        });
    }

    /**
     * Mark an intake as 'skipped' with a mandatory reason.
     *
     * @return array{ok: bool, http_status: int, code?: string, message: string, data?: array<string, mixed>}
     */
    public static function skipIntake(
        int $intakeId,
        int $userId,
        string $reason,
        ?string $comment = null,
        ?string $nowUtc = null
    ): array {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            return [
                'ok' => false,
                'http_status' => 422,
                'code' => 'REASON_REQUIRED',
                'message' => 'Le motif est obligatoire pour indiquer « Non donné ».',
            ];
        }

        $effectiveNowUtc = $nowUtc ?? gmdate('Y-m-d H:i:s');

        return Database::transaction(static function (PDO $pdo) use ($intakeId, $userId, $cleanReason, $comment, $effectiveNowUtc): array {
            $stmt = $pdo->prepare(
                'SELECT mi.*, m.name AS medication_name, u.first_name AS giver_first_name
                 FROM `medication_intakes` mi
                 INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
                 INNER JOIN `medications` m ON m.id = p.medication_id
                 LEFT JOIN `users` u ON u.id = mi.given_by
                 WHERE mi.id = :id AND mi.deleted_at IS NULL
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $intakeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'code' => 'INTAKE_NOT_FOUND',
                    'message' => 'Prise introuvable.',
                ];
            }

            if ($row['status'] !== 'pending') {
                $giverName = trim((string) ($row['giver_first_name'] ?? 'un membre de l’équipe'));
                $whenUtc = (string) ($row['given_at'] ?? $row['updated_at'] ?? $effectiveNowUtc);
                $whenParis = str_replace(':', 'h', ScheduleService::utcToLocalParis($whenUtc, 'H:i'));

                return [
                    'ok' => false,
                    'http_status' => 409,
                    'code' => 'ALREADY_PROCESSED',
                    'message' => sprintf('Déjà donné par %s à %s', $giverName, $whenParis),
                ];
            }

            $currentVersion = (int) $row['version'];
            $newVersion = $currentVersion + 1;
            $reasonLabel = self::SKIP_REASONS[$cleanReason] ?? $cleanReason;

            $upd = $pdo->prepare(
                'UPDATE `medication_intakes`
                 SET `status` = \'skipped\',
                     `given_at` = :given_at,
                     `reason` = :reason,
                     `comment` = :comment,
                     `given_by` = :given_by,
                     `version` = :new_version,
                     `updated_at` = :updated_at
                 WHERE `id` = :id
                   AND `status` = \'pending\'
                   AND `version` = :current_version'
            );
            $upd->execute([
                'given_at' => $effectiveNowUtc,
                'reason' => $reasonLabel,
                'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
                'given_by' => $userId,
                'new_version' => $newVersion,
                'updated_at' => $effectiveNowUtc,
                'id' => $intakeId,
                'current_version' => $currentVersion,
            ]);

            if ($upd->rowCount() === 0) {
                return [
                    'ok' => false,
                    'http_status' => 409,
                    'code' => 'ALREADY_PROCESSED',
                    'message' => 'Cette prise vient d’être traitée par un autre membre de l’équipe.',
                ];
            }

            $afterData = [
                'status' => 'skipped',
                'reason' => $reasonLabel,
                'comment' => $comment,
                'given_by' => $userId,
                'version' => $newVersion,
            ];

            AuditService::record(
                'intake.skip',
                'medication_intake',
                $intakeId,
                ['status' => $row['status'], 'version' => $currentVersion],
                $afterData,
                (int) $row['patient_id'],
                $userId
            );

            return [
                'ok' => true,
                'http_status' => 200,
                'message' => sprintf('Prise de %s marquée « Non donné » (%s).', (string) $row['medication_name'], $reasonLabel),
                'data' => array_merge($afterData, [
                    'id' => $intakeId,
                    'medication_name' => $row['medication_name'],
                ]),
            ];
        });
    }

    /**
     * Record an 'as-needed' (si besoin) medication intake after enforcing min_interval_h.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, http_status: int, code?: string, message: string, next_allowed_local?: string, data?: array<string, mixed>}
     */
    public static function giveAsNeeded(
        int $prescriptionId,
        int $userId,
        array $payload = [],
        ?string $nowUtc = null
    ): array {
        $effectiveNowUtc = $nowUtc ?? gmdate('Y-m-d H:i:s');
        $utcTz = new DateTimeZone('UTC');
        $nowDt = new DateTimeImmutable($effectiveNowUtc, $utcTz);

        return Database::transaction(static function (PDO $pdo) use ($prescriptionId, $userId, $payload, $effectiveNowUtc, $nowDt, $utcTz): array {
            $stmt = $pdo->prepare(
                'SELECT p.*, m.name AS medication_name
                 FROM `prescriptions` p
                 INNER JOIN `medications` m ON m.id = p.medication_id
                 WHERE p.id = :id AND p.deleted_at IS NULL AND p.is_active = 1
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $prescriptionId]);
            $rx = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rx) {
                return [
                    'ok' => false,
                    'http_status' => 404,
                    'code' => 'PRESCRIPTION_NOT_FOUND',
                    'message' => 'Prescription « si besoin » introuvable ou inactive.',
                ];
            }

            $minIntervalH = isset($rx['min_interval_h']) && (int) $rx['min_interval_h'] > 0
                ? (int) $rx['min_interval_h']
                : 0;

            if ($minIntervalH > 0) {
                $lastStmt = $pdo->prepare(
                    'SELECT `given_at`
                     FROM `medication_intakes`
                     WHERE `prescription_id` = :rx_id
                       AND `status` = \'given\'
                       AND `deleted_at` IS NULL
                     ORDER BY `given_at` DESC
                     LIMIT 1'
                );
                $lastStmt->execute(['rx_id' => $prescriptionId]);
                $lastRow = $lastStmt->fetch(PDO::FETCH_ASSOC);

                if ($lastRow && !empty($lastRow['given_at'])) {
                    $lastDt = new DateTimeImmutable((string) $lastRow['given_at'], $utcTz);
                    $nextAllowedDt = $lastDt->add(new DateInterval('PT' . $minIntervalH . 'H'));

                    if ($nowDt < $nextAllowedDt) {
                        $nextLocal = str_replace(':', 'h', ScheduleService::utcToLocalParis(
                            $nextAllowedDt->format('Y-m-d H:i:s'),
                            'H:i'
                        ));
                        $lastLocal = str_replace(':', 'h', ScheduleService::utcToLocalParis(
                            $lastDt->format('Y-m-d H:i:s'),
                            'H:i'
                        ));

                        return [
                            'ok' => false,
                            'http_status' => 422,
                            'code' => 'MIN_INTERVAL_VIOLATION',
                            'next_allowed_local' => $nextLocal,
                            'message' => sprintf(
                                'Intervalle minimal de %d h non respecté pour %s (dernière prise à %s). Prochaine prise possible à %s.',
                                $minIntervalH,
                                (string) $rx['medication_name'],
                                $lastLocal,
                                $nextLocal
                            ),
                        ];
                    }
                }
            }

            $givenDose = isset($payload['given_dose']) && is_numeric($payload['given_dose'])
                ? (float) $payload['given_dose']
                : (float) $rx['dose'];
            $reason = isset($payload['reason']) && trim((string) $payload['reason']) !== ''
                ? trim((string) $payload['reason'])
                : 'Prise si besoin';
            $comment = isset($payload['comment']) && trim((string) $payload['comment']) !== ''
                ? trim((string) $payload['comment'])
                : null;

            $ins = $pdo->prepare(
                'INSERT INTO `medication_intakes`
                 (`patient_id`, `prescription_id`, `prescription_time_id`, `scheduled_at`, `status`,
                  `given_at`, `given_dose`, `reason`, `comment`, `given_by`, `version`,
                  `created_at`, `updated_at`, `created_by`)
                 VALUES (:pid, :rx_id, NULL, :sched_at, \'given\', :given_at, :dose, :reason, :comment, :by, 1, :created_at, :updated_at, :created_by)'
            );
            $ins->execute([
                'pid' => (int) $rx['patient_id'],
                'rx_id' => $prescriptionId,
                'sched_at' => $effectiveNowUtc,
                'given_at' => $effectiveNowUtc,
                'dose' => $givenDose,
                'reason' => $reason,
                'comment' => $comment,
                'by' => $userId,
                'created_at' => $effectiveNowUtc,
                'updated_at' => $effectiveNowUtc,
                'created_by' => $userId,
            ]);
            $intakeId = (int) $pdo->lastInsertId();

            $after = [
                'id' => $intakeId,
                'prescription_id' => $prescriptionId,
                'status' => 'given',
                'given_at' => $effectiveNowUtc,
                'given_dose' => $givenDose,
                'reason' => $reason,
            ];

            AuditService::record(
                'intake.as_needed',
                'medication_intake',
                $intakeId,
                null,
                $after,
                (int) $rx['patient_id'],
                $userId
            );

            return [
                'ok' => true,
                'http_status' => 201,
                'message' => sprintf('Prise « si besoin » de %s enregistrée.', (string) $rx['medication_name']),
                'data' => $after,
            ];
        });
    }

    /**
     * Compute observance statistics for today, 7 days, and 30 days.
     *
     * @return array{
     *   today_given: int,
     *   today_total: int,
     *   today_pct: int,
     *   days_7_pct: int,
     *   days_7_given: int,
     *   days_7_total: int,
     *   days_30_pct: int,
     *   days_30_given: int,
     *   days_30_total: int,
     *   by_medication: array<int, array<string, mixed>>
     * }
     */
    public static function getObservanceSummary(int $patientId = 1, ?string $referenceUtc = null): array
    {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $refDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $localToday = ScheduleService::utcToLocalParis($refDt->format('Y-m-d H:i:s'), 'Y-m-d');
        $todayStartUtc = ScheduleService::localParisToUtc($localToday, '00:00');
        $todayEndUtc = ScheduleService::localParisToUtc($localToday, '23:59');

        // Today
        $tStmt = $pdo->prepare(
            'SELECT `status`, COUNT(*) AS cnt
             FROM `medication_intakes`
             WHERE `patient_id` = :pid
               AND `prescription_time_id` IS NOT NULL
               AND `scheduled_at` BETWEEN :from_utc AND :to_utc
               AND `deleted_at` IS NULL
             GROUP BY `status`'
        );
        $tStmt->execute(['pid' => $patientId, 'from_utc' => $todayStartUtc, 'to_utc' => $todayEndUtc]);
        $tCounts = ['given' => 0, 'skipped' => 0, 'pending' => 0];
        foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tCounts[(string) $r['status']] = (int) $r['cnt'];
        }
        $todayTotal = $tCounts['given'] + $tCounts['skipped'] + $tCounts['pending'];
        $todayPct = $todayTotal > 0 ? (int) round(($tCounts['given'] / $todayTotal) * 100) : 100;

        // 7d & 30d (up to nowUtc)
        $nowStr = $refDt->format('Y-m-d H:i:s');
        $d7Start = $refDt->modify('-7 days')->format('Y-m-d H:i:s');
        $d30Start = $refDt->modify('-30 days')->format('Y-m-d H:i:s');

        $calcWindow = static function (string $from) use ($pdo, $patientId, $nowStr): array {
            $s = $pdo->prepare(
                'SELECT `status`, COUNT(*) AS cnt
                 FROM `medication_intakes`
                 WHERE `patient_id` = :pid
                   AND `prescription_time_id` IS NOT NULL
                   AND `scheduled_at` BETWEEN :from_utc AND :to_utc
                   AND `deleted_at` IS NULL
                 GROUP BY `status`'
            );
            $s->execute(['pid' => $patientId, 'from_utc' => $from, 'to_utc' => $nowStr]);
            $c = ['given' => 0, 'skipped' => 0, 'pending' => 0];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $c[(string) $row['status']] = (int) $row['cnt'];
            }
            $tot = $c['given'] + $c['skipped'] + $c['pending'];
            return [
                'given' => $c['given'],
                'total' => $tot,
                'pct' => $tot > 0 ? (int) round(($c['given'] / $tot) * 100) : 100,
            ];
        };

        $w7 = $calcWindow($d7Start);
        $w30 = $calcWindow($d30Start);

        // Per medication over 30 days
        $byMedStmt = $pdo->prepare(
            'SELECT m.id AS medication_id,
                    m.name AS medication_name,
                    m.form,
                    p.dose,
                    p.unit,
                    SUM(CASE WHEN mi.status = \'given\' THEN 1 ELSE 0 END) AS given_count,
                    SUM(CASE WHEN mi.status = \'skipped\' THEN 1 ELSE 0 END) AS skipped_count,
                    COUNT(mi.id) AS total_count
             FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             INNER JOIN `medications` m ON m.id = p.medication_id
             WHERE mi.patient_id = :pid
               AND mi.prescription_time_id IS NOT NULL
               AND mi.scheduled_at BETWEEN :from_utc AND :to_utc
               AND mi.deleted_at IS NULL
             GROUP BY m.id, m.name, m.form, p.dose, p.unit
             ORDER BY m.name ASC'
        );
        $byMedStmt->execute(['pid' => $patientId, 'from_utc' => $d30Start, 'to_utc' => $nowStr]);
        $byMedication = [];
        foreach ($byMedStmt->fetchAll(PDO::FETCH_ASSOC) as $mRow) {
            $tot = (int) $mRow['total_count'];
            $giv = (int) $mRow['given_count'];
            $mRow['observance_pct'] = $tot > 0 ? (int) round(($giv / $tot) * 100) : 100;
            $byMedication[] = $mRow;
        }

        return [
            'today_given' => $tCounts['given'],
            'today_total' => $todayTotal,
            'today_pct' => $todayPct,
            'days_7_pct' => $w7['pct'],
            'days_7_given' => $w7['given'],
            'days_7_total' => $w7['total'],
            'days_30_pct' => $w30['pct'],
            'days_30_given' => $w30['given'],
            'days_30_total' => $w30['total'],
            'by_medication' => $byMedication,
        ];
    }
}
