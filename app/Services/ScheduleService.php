<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Generates idempotent medication_intakes and measure_expectations in UTC from Europe/Paris
 * local schedules (handling summer/winter DST transitions accurately) and regenerates future
 * pending occurrences when a prescription or schedule is modified.
 */
final class ScheduleService
{
    /**
     * Convert a local Europe/Paris date (YYYY-MM-DD) and time (HH:MM) to a UTC DATETIME string.
     */
    public static function localParisToUtc(string $localDate, string $hhMm): string
    {
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $cleanTime = substr(trim($hhMm), 0, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $cleanTime)) {
            $cleanTime = '08:00';
        }

        $localDt = new DateTimeImmutable($localDate . ' ' . $cleanTime . ':00', $parisTz);
        return $localDt->setTimezone($utcTz)->format('Y-m-d H:i:s');
    }

    public static function localDateAndTimeToUtc(string $localDate, string $hhMm): string
    {
        return self::localParisToUtc($localDate, $hhMm);
    }

    /**
     * Generate occurrences for a DateTimeImmutable window [from, to].
     *
     * @return array{intakes_created: int, expectations_created: int, intakes_inserted: int, expectations_inserted: int}
     */
    public static function generateForWindow(DateTimeImmutable $from, DateTimeImmutable $to, int $patientId = 1): array
    {
        $utcTz = new DateTimeZone('UTC');
        $res = self::generateForRange(
            $from->setTimezone($utcTz)->format('Y-m-d H:i:s'),
            $to->setTimezone($utcTz)->format('Y-m-d H:i:s'),
            $patientId
        );
        return [
            'intakes_created' => $res['intakes_inserted'],
            'expectations_created' => $res['expectations_inserted'],
            'intakes_inserted' => $res['intakes_inserted'],
            'expectations_inserted' => $res['expectations_inserted'],
        ];
    }

    /**
     * Convert a UTC DATETIME string to Europe/Paris formatted time (HH:MM or custom format).
     */
    public static function utcToLocalParis(string $utcDateTime, string $format = 'H:i'): string
    {
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $dt = new DateTimeImmutable($utcDateTime, $utcTz);
        return $dt->setTimezone($parisTz)->format($format);
    }

    /**
     * Generate medication_intakes and measure_expectations for the next 48 hours (plus current local day)
     * idempotently.
     *
     * @return array{intakes_inserted: int, expectations_inserted: int}
     */
    public static function generateUpcoming48h(?string $referenceUtc = null, int $patientId = 1): array
    {
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $refDtUtc = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $localNow = $refDtUtc->setTimezone($parisTz);
        $startLocalDate = $localNow->format('Y-m-d');
        $startDayStartUtc = (new DateTimeImmutable($startLocalDate . ' 00:00:00', $parisTz))
            ->setTimezone($utcTz)
            ->format('Y-m-d H:i:s');
        $endUtc = $refDtUtc->add(new DateInterval('PT48H'))->format('Y-m-d H:i:s');

        return self::generateForRange($startDayStartUtc, $endUtc, $patientId);
    }

    /**
     * Ensure occurrences exist for a specific local date (YYYY-MM-DD in Europe/Paris).
     *
     * @return array{intakes_inserted: int, expectations_inserted: int}
     */
    public static function ensureForLocalDate(string $localDate, int $patientId = 1): array
    {
        $fromUtc = self::localParisToUtc($localDate, '00:00');
        $toUtc = self::localParisToUtc($localDate, '23:59');
        return self::generateForRange($fromUtc, $toUtc, $patientId);
    }

    /**
     * Generate medication_intakes and measure_expectations for any UTC range [fromUtc, toUtc] idempotently.
     *
     * @return array{intakes_inserted: int, expectations_inserted: int}
     */
    public static function generateForRange(
        string $fromUtc,
        string $toUtc,
        int $patientId = 1,
        ?int $onlyPrescriptionId = null
    ): array {
        $pdo = Database::connection();
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $fromDtUtc = new DateTimeImmutable($fromUtc, $utcTz);
        $toDtUtc = new DateTimeImmutable($toUtc, $utcTz);

        // Determine local Paris dates spanned by [fromDtUtc, toDtUtc]
        $cursorLocal = new DateTimeImmutable(
            $fromDtUtc->setTimezone($parisTz)->format('Y-m-d') . ' 12:00:00',
            $parisTz
        );
        $endLocalDateStr = $toDtUtc->setTimezone($parisTz)->format('Y-m-d');

        $localDates = [];
        while ($cursorLocal->format('Y-m-d') <= $endLocalDateStr) {
            $localDates[] = [
                'date' => $cursorLocal->format('Y-m-d'),
                'iso_dow' => (string) $cursorLocal->format('N'), // '1' (Mon) .. '7' (Sun)
            ];
            $cursorLocal = $cursorLocal->add(new DateInterval('P1D'));
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $intakesInserted = 0;
        $expectationsInserted = 0;

        // 1. Fetch active scheduled prescriptions (as_needed = 0) and their prescription_times
        $rxSql = 'SELECT p.*, pt.id AS ptime_id, pt.scheduled_time, pt.dose_override
                  FROM `prescriptions` p
                  INNER JOIN `prescription_times` pt ON pt.prescription_id = p.id AND pt.deleted_at IS NULL
                  INNER JOIN `medications` m ON m.id = p.medication_id AND m.deleted_at IS NULL AND m.is_active = 1
                  WHERE p.patient_id = :patient_id
                    AND p.is_active = 1
                    AND p.as_needed = 0
                    AND p.deleted_at IS NULL';
        $rxParams = ['patient_id' => $patientId];
        if ($onlyPrescriptionId !== null) {
            $rxSql .= ' AND p.id = :rx_id';
            $rxParams['rx_id'] = $onlyPrescriptionId;
        }

        $rxStmt = $pdo->prepare($rxSql);
        $rxStmt->execute($rxParams);
        $rxRows = $rxStmt->fetchAll(PDO::FETCH_ASSOC);

        $insertIntakeStmt = $pdo->prepare(
            'INSERT IGNORE INTO `medication_intakes`
             (`patient_id`, `prescription_id`, `prescription_time_id`, `scheduled_at`, `status`, `version`, `created_at`, `updated_at`, `created_by`)
             VALUES (:patient_id, :prescription_id, :ptime_id, :scheduled_at, \'pending\', 1, :created_at, :updated_at, 1)'
        );

        foreach ($localDates as $dayInfo) {
            $localDate = $dayInfo['date'];
            $dow = $dayInfo['iso_dow'];

            foreach ($rxRows as $rx) {
                $startDate = (string) $rx['start_date'];
                $endDate = $rx['end_date'] !== null ? (string) $rx['end_date'] : null;

                if ($localDate < $startDate) {
                    continue;
                }
                if ($endDate !== null && $endDate !== '' && $localDate > $endDate) {
                    continue;
                }

                $daysMask = array_map('trim', explode(',', (string) ($rx['days_mask'] ?? '1,2,3,4,5,6,7')));
                if (!in_array($dow, $daysMask, true)) {
                    continue;
                }

                $scheduledUtc = self::localParisToUtc($localDate, (string) $rx['scheduled_time']);
                if ($scheduledUtc < $fromDtUtc->format('Y-m-d H:i:s') || $scheduledUtc > $toDtUtc->format('Y-m-d H:i:s')) {
                    continue;
                }

                $insertIntakeStmt->execute([
                    'patient_id' => $patientId,
                    'prescription_id' => (int) $rx['id'],
                    'ptime_id' => (int) $rx['ptime_id'],
                    'scheduled_at' => $scheduledUtc,
                    'created_at' => $nowUtc,
                    'updated_at' => $nowUtc,
                ]);
                $intakesInserted += $insertIntakeStmt->rowCount();
            }
        }

        // 2. Generate measure_expectations if not restricted to a single prescription
        if ($onlyPrescriptionId === null) {
            $msStmt = $pdo->prepare(
                'SELECT ms.*
                 FROM `measure_schedules` ms
                 INNER JOIN `measure_types` mt ON mt.id = ms.measure_type_id AND mt.deleted_at IS NULL AND mt.is_active = 1
                 WHERE ms.patient_id = :patient_id
                   AND ms.is_active = 1
                   AND ms.deleted_at IS NULL'
            );
            $msStmt->execute(['patient_id' => $patientId]);
            $schedules = $msStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertExpStmt = $pdo->prepare(
                'INSERT IGNORE INTO `measure_expectations`
                 (`patient_id`, `measure_schedule_id`, `measure_type_id`, `scheduled_at`, `status`, `created_at`, `updated_at`, `created_by`)
                 VALUES (:patient_id, :schedule_id, :type_id, :scheduled_at, \'upcoming\', :created_at, :updated_at, 1)'
            );

            foreach ($localDates as $dayInfo) {
                $localDate = $dayInfo['date'];
                $dow = $dayInfo['iso_dow'];

                foreach ($schedules as $ms) {
                    $daysMask = array_map('trim', explode(',', (string) ($ms['days_mask'] ?? '1,2,3,4,5,6,7')));
                    if (!in_array($dow, $daysMask, true)) {
                        continue;
                    }

                    $scheduledUtc = self::localParisToUtc($localDate, (string) $ms['scheduled_time']);
                    if ($scheduledUtc < $fromDtUtc->format('Y-m-d H:i:s') || $scheduledUtc > $toDtUtc->format('Y-m-d H:i:s')) {
                        continue;
                    }

                    $insertExpStmt->execute([
                        'patient_id' => $patientId,
                        'schedule_id' => (int) $ms['id'],
                        'type_id' => (int) $ms['measure_type_id'],
                        'scheduled_at' => $scheduledUtc,
                        'created_at' => $nowUtc,
                        'updated_at' => $nowUtc,
                    ]);
                    $expectationsInserted += $insertExpStmt->rowCount();
                }
            }

            self::syncExpectationStatuses($nowUtc, $patientId);
        }

        return [
            'intakes_inserted' => $intakesInserted,
            'expectations_inserted' => $expectationsInserted,
        ];
    }

    /**
     * When a prescription is modified, remove only future 'pending' occurrences (from referenceUtc onwards)
     * without touching past occurrences or occurrences already 'given'/'skipped', then regenerate.
     *
     * @return array{deleted_pending: int, regenerated: int}
     */
    public static function regenerateForPrescription(
        int $prescriptionId,
        ?string $referenceUtc = null,
        int $patientId = 1
    ): array {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $refDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $fromUtc = $refDt->format('Y-m-d H:i:s');
        $toUtc = $refDt->add(new DateInterval('PT48H'))->format('Y-m-d H:i:s');

        // Delete only future pending intakes (or orphaned pending intakes whose prescription_time was soft-deleted)
        $delStmt = $pdo->prepare(
            'DELETE FROM `medication_intakes`
             WHERE `prescription_id` = :rx_id
               AND `status` = \'pending\'
               AND `scheduled_at` >= :from_utc'
        );
        $delStmt->execute([
            'rx_id' => $prescriptionId,
            'from_utc' => $fromUtc,
        ]);
        $deletedCount = $delStmt->rowCount();

        $gen = self::generateForRange($fromUtc, $toUtc, $patientId, $prescriptionId);

        return [
            'deleted_pending' => $deletedCount,
            'regenerated' => $gen['intakes_inserted'],
        ];
    }

    /**
     * Link measures to matching measure_expectations and update 'upcoming' -> 'late' after window.
     */
    public static function syncExpectationStatuses(?string $referenceUtc = null, int $patientId = 1): void
    {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $refDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $lateCutoffUtc = $refDt->modify('-30 minutes')->format('Y-m-d H:i:s');
        $nowUtc = $refDt->format('Y-m-d H:i:s');

        $updLate = $pdo->prepare(
            'UPDATE `measure_expectations`
             SET `status` = \'late\', `updated_at` = :now_utc
             WHERE `patient_id` = :patient_id
               AND `measure_id` IS NULL
               AND `status` = \'upcoming\'
               AND `scheduled_at` < :cutoff_utc
               AND `deleted_at` IS NULL'
        );
        $updLate->execute([
            'now_utc' => $nowUtc,
            'patient_id' => $patientId,
            'cutoff_utc' => $lateCutoffUtc,
        ]);
    }

    /**
     * Attach a newly recorded measure to the closest open expectation of the same type within ±120 minutes.
     */
    public static function fulfillExpectationWithMeasure(
        int $measureId,
        int $measureTypeId,
        string $measuredAtUtc,
        int $patientId = 1
    ): ?int {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $dt = new DateTimeImmutable($measuredAtUtc, $utcTz);
        $minUtc = $dt->modify('-120 minutes')->format('Y-m-d H:i:s');
        $maxUtc = $dt->modify('+120 minutes')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT `id`
             FROM `measure_expectations`
             WHERE `patient_id` = :patient_id
               AND `measure_type_id` = :type_id
               AND `measure_id` IS NULL
               AND `deleted_at` IS NULL
               AND `scheduled_at` BETWEEN :min_utc AND :max_utc
             ORDER BY ABS(TIMESTAMPDIFF(SECOND, `scheduled_at`, :measured_at)) ASC
             LIMIT 1'
        );
        $stmt->execute([
            'patient_id' => $patientId,
            'type_id' => $measureTypeId,
            'min_utc' => $minUtc,
            'max_utc' => $maxUtc,
            'measured_at' => $measuredAtUtc,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $expId = (int) $row['id'];
        $upd = $pdo->prepare(
            'UPDATE `measure_expectations`
             SET `status` = \'done\', `measure_id` = :measure_id, `updated_at` = :now_utc
             WHERE `id` = :id'
        );
        $upd->execute([
            'measure_id' => $measureId,
            'now_utc' => gmdate('Y-m-d H:i:s'),
            'id' => $expId,
        ]);

        return $expId;
    }
}
