<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Builds the structured daily care program for a local Europe/Paris date:
 * - Caregiver shift chronology (done, late, highlighted current task, upcoming)
 * - Today screen (E04) grouped by moment (matin, midi, soir, coucher)
 * - Key vitals and 7-day glycemia sparkline for Admin Home
 */
final class ShiftTimelineService
{
    /**
     * Build the full day schedule of intakes, expected measures, and recorded meals for a local date.
     *
     * @return array{
     *   date: string,
     *   done: array<int, array<string, mixed>>,
     *   late: array<int, array<string, mixed>>,
     *   current: array<string, mixed>|null,
     *   upcoming: array<int, array<string, mixed>>,
     *   by_moment: array<string, array<int, array<string, mixed>>>,
     *   as_needed: array<int, array<string, mixed>>
     * }
     */
    public static function getDayProgram(
        string $localDate,
        int $patientId = 1,
        ?string $referenceUtc = null
    ): array {
        ScheduleService::ensureForLocalDate($localDate, $patientId);

        $pdo = Database::connection();
        $fromUtc = ScheduleService::localParisToUtc($localDate, '00:00');
        $toUtc = ScheduleService::localParisToUtc($localDate, '23:59');
        $nowUtc = $referenceUtc ?? gmdate('Y-m-d H:i:s');

        $tasks = [];

        // 1. Medication intakes for the day
        $iStmt = $pdo->prepare(
            'SELECT mi.*,
                    p.dose AS rx_dose,
                    p.unit AS rx_unit,
                    p.insulin_kind,
                    p.dose_mode,
                    p.correction_table,
                    p.instructions AS rx_instructions,
                    pt.moment_label,
                    pt.dose_override,
                    m.name AS medication_name,
                    m.form AS medication_form,
                    u.first_name AS giver_first_name
             FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             INNER JOIN `medications` m ON m.id = p.medication_id
             LEFT JOIN `prescription_times` pt ON pt.id = mi.prescription_time_id
             LEFT JOIN `users` u ON u.id = mi.given_by
             WHERE mi.patient_id = :pid
               AND mi.scheduled_at BETWEEN :from_utc AND :to_utc
               AND mi.deleted_at IS NULL
             ORDER BY mi.scheduled_at ASC, mi.id ASC'
        );
        $iStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);

        foreach ($iStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $disp = IntakeService::computeDisplayStatus($row, $nowUtc);
            $timeLocal = ScheduleService::utcToLocalParis((string) $row['scheduled_at'], 'H:i');
            $dose = $row['given_dose'] !== null
                ? (float) $row['given_dose']
                : ($row['dose_override'] !== null ? (float) $row['dose_override'] : (float) $row['rx_dose']);
            $doseFormatted = str_replace('.', ',', (string) (floor($dose) == $dose ? (int) $dose : $dose)) . ' ' . (string) $row['rx_unit'];

            $moment = (string) ($row['moment_label'] ?: self::deduceMoment($timeLocal));

            $tasks[] = [
                'task_type' => 'intake',
                'kind' => 'intake',
                'id' => (int) $row['id'],
                'intake_id' => (int) $row['id'],
                'prescription_id' => (int) $row['prescription_id'],
                'scheduled_utc' => (string) $row['scheduled_at'],
                'time_local' => $timeLocal,
                'time' => $timeLocal,
                'moment' => $moment,
                'module_class' => 'meds',
                'module' => 'meds',
                'icon' => $row['insulin_kind'] !== null ? 'syringe' : 'pill',
                'title' => (string) $row['medication_name'],
                'subtitle' => ((string) $row['dose_mode'] === 'tableau')
                    ? 'Dose selon tableau du responsable (' . $doseFormatted . ' réf.)'
                    : $doseFormatted . ' · ' . (string) $row['medication_form'],
                'dose_display' => $doseFormatted,
                'dose_value' => $dose,
                'dose' => $dose,
                'unit' => (string) $row['rx_unit'],
                'insulin_kind' => $row['insulin_kind'],
                'dose_mode' => (string) $row['dose_mode'],
                'is_variable_insulin' => (string) $row['dose_mode'] === 'tableau',
                'correction_table' => is_string($row['correction_table'] ?? null)
                    ? (json_decode((string) $row['correction_table'], true) ?: [])
                    : [],
                'status' => $disp['display_status'], // 'given', 'skipped', 'late', 'due', 'upcoming'
                'timeline_state' => in_array($disp['display_status'], ['given', 'skipped'], true) ? 'done' : $disp['display_status'],
                'status_label' => $disp['label'],
                'status_icon' => $disp['icon'],
                'status_badge_class' => $disp['badge_class'],
                'badge_class' => $disp['badge_class'],
                'is_done' => in_array($disp['display_status'], ['given', 'skipped'], true),
                'given_at_local' => !empty($row['given_at']) ? ScheduleService::utcToLocalParis((string) $row['given_at'], 'H:i') : null,
                'giver_name' => $row['giver_first_name'] ?? null,
                'reason' => $row['reason'] ?? null,
                'injection_site' => $row['injection_site'] ?? null,
                'version' => (int) $row['version'],
            ];
        }

        // 2. Expected measurements for the day
        $eStmt = $pdo->prepare(
            'SELECT me.*,
                    ms.context,
                    ms.label AS schedule_label,
                    mt.code AS type_code,
                    mt.label AS type_label,
                    mt.unit,
                    mt.decimals,
                    mt.icon_name,
                    m.value AS recorded_value,
                    m.value2 AS recorded_value2,
                    m.measured_at,
                    m.alert_level,
                    u.first_name AS recorder_first_name
             FROM `measure_expectations` me
             INNER JOIN `measure_schedules` ms ON ms.id = me.measure_schedule_id
             INNER JOIN `measure_types` mt ON mt.id = me.measure_type_id
             LEFT JOIN `measures` m ON m.id = me.measure_id AND m.deleted_at IS NULL
             LEFT JOIN `users` u ON u.id = m.recorded_by
             WHERE me.patient_id = :pid
               AND me.scheduled_at BETWEEN :from_utc AND :to_utc
               AND me.deleted_at IS NULL
             ORDER BY me.scheduled_at ASC, me.id ASC'
        );
        $eStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);

        $utcTz = new DateTimeZone('UTC');
        $nowDt = new DateTimeImmutable($nowUtc, $utcTz);

        foreach ($eStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $timeLocal = ScheduleService::utcToLocalParis((string) $row['scheduled_at'], 'H:i');
            $moment = self::deduceMoment($timeLocal);
            $schedDt = new DateTimeImmutable((string) $row['scheduled_at'], $utcTz);
            $diffSec = $nowDt->getTimestamp() - $schedDt->getTimestamp();

            if ($row['measure_id'] !== null && $row['recorded_value'] !== null) {
                $status = 'done';
                $statusLabel = 'Mesurée';
                $statusIcon = 'check-circle';
                $badgeClass = 'pc-status-pill--ok';
                $decimals = (int) ($row['decimals'] ?? 1);
                $valStr = number_format((float) $row['recorded_value'], $decimals, ',', ' ');
                if ($row['recorded_value2'] !== null && (string) $row['type_code'] === 'blood_pressure') {
                    $valStr = sprintf('%d/%d', (int) round((float) $row['recorded_value']), (int) round((float) $row['recorded_value2']));
                }
                $subtitle = sprintf('Relevé : %s %s', $valStr, (string) $row['unit']);
            } elseif ($diffSec > 1800) {
                $status = 'late';
                $statusLabel = 'En retard';
                $statusIcon = 'alert-triangle';
                $badgeClass = 'pc-status-pill--critical';
                $subtitle = TimelineService::contextLabel((string) $row['context']);
            } elseif ($diffSec >= -1800) {
                $status = 'due';
                $statusLabel = 'À saisir';
                $statusIcon = 'bell';
                $badgeClass = 'pc-status-pill--info';
                $subtitle = TimelineService::contextLabel((string) $row['context']);
            } else {
                $status = 'upcoming';
                $statusLabel = 'À venir';
                $statusIcon = 'clock';
                $badgeClass = 'pc-status-pill--neutral';
                $subtitle = TimelineService::contextLabel((string) $row['context']);
            }

            $tasks[] = [
                'task_type' => 'measure',
                'kind' => 'measure_expectation',
                'id' => (int) $row['id'],
                'expectation_id' => (int) $row['id'],
                'measure_type_id' => (int) $row['measure_type_id'],
                'measure_code' => (string) $row['type_code'],
                'type_code' => (string) $row['type_code'],
                'context' => (string) $row['context'],
                'scheduled_utc' => (string) $row['scheduled_at'],
                'time_local' => $timeLocal,
                'time' => $timeLocal,
                'moment' => $moment,
                'module_class' => 'vitals',
                'module' => 'vitals',
                'icon' => (string) ($row['icon_name'] ?: 'activity'),
                'title' => (string) ($row['schedule_label'] ?: $row['type_label']),
                'subtitle' => $subtitle,
                'status' => $status,
                'timeline_state' => $status,
                'status_label' => $statusLabel,
                'status_icon' => $statusIcon,
                'status_badge_class' => $badgeClass,
                'badge_class' => $badgeClass,
                'is_done' => $status === 'done',
                'given_at_local' => !empty($row['measured_at']) ? ScheduleService::utcToLocalParis((string) $row['measured_at'], 'H:i') : null,
                'giver_name' => $row['recorder_first_name'] ?? null,
            ];
        }

        // 3. Recorded meals for the day
        $mealStmt = $pdo->prepare(
            'SELECT ml.*, u.first_name AS recorder_first_name
             FROM `meals` ml
             INNER JOIN `users` u ON u.id = ml.recorded_by
             WHERE ml.patient_id = :pid
               AND ml.eaten_at BETWEEN :from_utc AND :to_utc
               AND ml.deleted_at IS NULL
             ORDER BY ml.eaten_at ASC'
        );
        $mealStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
        foreach ($mealStmt->fetchAll(PDO::FETCH_ASSOC) as $mRow) {
            $timeLocal = ScheduleService::utcToLocalParis((string) $mRow['eaten_at'], 'H:i');
            $moment = self::deduceMoment($timeLocal);
            $mLabel = MealService::MEAL_TYPES[(string) $mRow['meal_type']] ?? 'Repas';
            $portion = (int) $mRow['portion_eaten'];

            $tasks[] = [
                'task_type' => 'meal',
                'kind' => 'meal',
                'id' => (int) $mRow['id'],
                'meal_id' => (int) $mRow['id'],
                'scheduled_utc' => (string) $mRow['eaten_at'],
                'time_local' => $timeLocal,
                'time' => $timeLocal,
                'moment' => $moment,
                'module_class' => 'meals',
                'module' => 'meals',
                'icon' => 'utensils',
                'title' => $mLabel,
                'subtitle' => sprintf('%s · Portion : %d %%', (string) $mRow['description'], $portion),
                'status' => 'done',
                'timeline_state' => 'done',
                'status_label' => sprintf('Pris (%d %%)', $portion),
                'status_icon' => 'check-circle',
                'status_badge_class' => 'pc-status-pill--ok',
                'badge_class' => 'pc-status-pill--ok',
                'is_done' => true,
                'given_at_local' => $timeLocal,
                'giver_name' => $mRow['recorder_first_name'] ?? null,
                'detail_url' => '/meals/' . (int) $mRow['id'],
            ];
        }

        // Sort all tasks chronologically by scheduled_utc
        usort($tasks, static fn(array $a, array $b): int => strcmp($a['scheduled_utc'], $b['scheduled_utc']));

        // Partition into done, late, current (highlighted next actionable task), upcoming, and by_moment
        $done = [];
        $late = [];
        $current = null;
        $upcoming = [];
        $byMoment = [
            'matin' => [],
            'midi' => [],
            'soir' => [],
            'coucher' => [],
        ];

        foreach ($tasks as $t) {
            $mKey = isset($byMoment[$t['moment']]) ? $t['moment'] : 'matin';
            $byMoment[$mKey][] = $t;

            if ($t['is_done']) {
                $done[] = $t;
                continue;
            }

            if ($t['status'] === 'late') {
                if ($current === null) {
                    $current = $t;
                } else {
                    $late[] = $t;
                }
                continue;
            }

            if ($current === null) {
                $current = $t;
            } else {
                $upcoming[] = $t;
            }
        }

        // 4. 'As-needed' prescriptions with next-allowed check
        $anStmt = $pdo->prepare(
            'SELECT p.*, m.name AS medication_name, m.form AS medication_form
             FROM `prescriptions` p
             INNER JOIN `medications` m ON m.id = p.medication_id
             WHERE p.patient_id = :pid
               AND p.as_needed = 1
               AND p.is_active = 1
               AND p.deleted_at IS NULL'
        );
        $anStmt->execute(['pid' => $patientId]);
        $asNeededList = [];
        foreach ($anStmt->fetchAll(PDO::FETCH_ASSOC) as $an) {
            $lastStmt = $pdo->prepare(
                'SELECT `given_at`
                 FROM `medication_intakes`
                 WHERE `prescription_id` = :rx_id AND `status` = \'given\' AND `deleted_at` IS NULL
                 ORDER BY `given_at` DESC LIMIT 1'
            );
            $lastStmt->execute(['rx_id' => (int) $an['id']]);
            $lastRow = $lastStmt->fetch(PDO::FETCH_ASSOC);

            $canGiveNow = true;
            $nextAllowedLocal = null;
            $minH = (int) ($an['min_interval_h'] ?? 0);
            if ($lastRow && !empty($lastRow['given_at']) && $minH > 0) {
                $lastDt = new DateTimeImmutable((string) $lastRow['given_at'], $utcTz);
                $nextDt = $lastDt->add(new DateInterval('PT' . $minH . 'H'));
                if ($nowDt < $nextDt) {
                    $canGiveNow = false;
                    $nextAllowedLocal = str_replace(':', 'h', ScheduleService::utcToLocalParis($nextDt->format('Y-m-d H:i:s'), 'H:i'));
                }
            }

            $an['prescription_id'] = (int) $an['id'];
            $an['can_give'] = $canGiveNow;
            $an['can_give_now'] = $canGiveNow;
            $an['next_allowed_local'] = $nextAllowedLocal;
            $asNeededList[] = $an;
        }

        $totalTasks = count($tasks);
        $doneCount = count($done);
        $lateCount = count($late) + ($current !== null && $current['status'] === 'late' ? 1 : 0);
        $progressPct = $totalTasks > 0 ? (int) round(($doneCount / $totalTasks) * 100) : 100;

        $momentsStructured = [
            'matin' => ['label' => 'Matin (06h – 11h30)', 'icon' => 'sun', 'items' => $byMoment['matin']],
            'midi' => ['label' => 'Midi (11h30 – 15h)', 'icon' => 'utensils', 'items' => $byMoment['midi']],
            'soir' => ['label' => 'Soir (15h – 20h30)', 'icon' => 'clock', 'items' => $byMoment['soir']],
            'coucher' => ['label' => 'Coucher & Nuit', 'icon' => 'moon', 'items' => $byMoment['coucher']],
        ];

        return [
            'date' => $localDate,
            'done' => $done,
            'late' => $late,
            'current' => $current,
            'upcoming' => $upcoming,
            'flat' => $tasks,
            'by_moment' => $byMoment,
            'moments' => $momentsStructured,
            'as_needed' => $asNeededList,
            'counts' => [
                'total' => $totalTasks,
                'done' => $doneCount,
                'late' => $lateCount,
                'due' => $current !== null && $current['status'] === 'due' ? 1 : 0,
                'pending' => max(0, $totalTasks - $doneCount),
                'progress_pct' => $progressPct,
            ],
        ];
    }

    /**
     * Get the latest key vitals (glycemia, blood_pressure, temperature) and 7-day glycemia chart data for Admin Home.
     *
     * @return array{
     *   vitals: array<string, array<string, mixed>|null>,
     *   glycemia_7d: array{
     *     points: array<int, array{x: string, y: float, alertLevel: string, statusLabel: string}>,
     *     avg: float,
     *     min: float,
     *     max: float,
     *     low_count: int,
     *     high_count: int,
     *     total: int,
     *     summary_text: string
     *   }
     * }
     */
    public static function getAdminKeyMetrics(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $vitals = [
            'glycemia' => null,
            'blood_pressure' => null,
            'temperature' => null,
            'spo2' => null,
            'weight' => null,
        ];

        foreach (array_keys($vitals) as $code) {
            $stmt = $pdo->prepare(
                'SELECT m.*, mt.code, mt.label, mt.unit, mt.decimals,
                        mt.crit_low, mt.low, mt.high, mt.crit_high,
                        mt.secondary_crit_low, mt.secondary_low, mt.secondary_high, mt.secondary_crit_high
                 FROM `measures` m
                 INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
                 WHERE m.patient_id = :pid
                   AND mt.code = :code
                   AND m.deleted_at IS NULL
                 ORDER BY m.measured_at DESC
                 LIMIT 1'
            );
            $stmt->execute(['pid' => $patientId, 'code' => $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $eval = ThresholdService::evaluate(
                    $row,
                    (float) $row['value'],
                    (string) $row['context'],
                    $row['value2'] !== null ? (float) $row['value2'] : null
                );
                $decimals = (int) ($row['decimals'] ?? 1);
                if ($code === 'blood_pressure' && $row['value2'] !== null) {
                    $row['formatted_value'] = sprintf('%d/%d', (int) round((float) $row['value']), (int) round((float) $row['value2']));
                } else {
                    $row['formatted_value'] = number_format((float) $row['value'], $decimals, ',', ' ');
                }
                $row['formatted'] = $row['formatted_value'];
                $row['context_label'] = TimelineService::contextLabel((string) $row['context']);
                $row['time_local'] = ScheduleService::utcToLocalParis((string) $row['measured_at'], 'd/m à H:i');
                $row['eval'] = $eval;
                $vitals[$code] = $row;
            }
        }

        // 7-day glycemia series
        $from7dUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-7 days')
            ->format('Y-m-d H:i:s');

        $gStmt = $pdo->prepare(
            'SELECT m.`value`, m.`context`, m.`alert_level`, m.`threshold_level`, m.`measured_at`
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND mt.code = \'glycemia\'
               AND m.measured_at >= :from_utc
               AND m.deleted_at IS NULL
             ORDER BY m.measured_at ASC'
        );
        $gStmt->execute(['pid' => $patientId, 'from_utc' => $from7dUtc]);
        $gRows = $gStmt->fetchAll(PDO::FETCH_ASSOC);

        $points = [];
        $sum = 0.0;
        $min = 99.0;
        $max = 0.0;
        $lowCount = 0;
        $highCount = 0;

        foreach ($gRows as $gr) {
            $val = (float) $gr['value'];
            $sum += $val;
            if ($val < $min) {
                $min = $val;
            }
            if ($val > $max) {
                $max = $val;
            }
            $tLevel = (string) $gr['threshold_level'];
            if (in_array($tLevel, ['crit_low', 'low'], true)) {
                $lowCount++;
            } elseif (in_array($tLevel, ['high', 'crit_high'], true)) {
                $highCount++;
            }

            $statusLabel = match ($tLevel) {
                'crit_low' => 'Hypoglycémie critique',
                'low' => 'Bas',
                'high' => 'Au-dessus de la cible',
                'crit_high' => 'Hyperglycémie critique',
                default => 'Dans la cible',
            };

            $points[] = [
                'x' => ScheduleService::utcToLocalParis((string) $gr['measured_at'], 'd/m H:i'),
                'y' => $val,
                'alertLevel' => (string) $gr['alert_level'],
                'alert_level' => (string) $gr['alert_level'],
                'statusLabel' => $statusLabel,
                'context' => $statusLabel,
            ];
        }

        $total = count($points);
        $avg = $total > 0 ? round($sum / $total, 2) : 1.15;
        if ($total === 0) {
            $min = 1.00;
            $max = 1.30;
        }

        $summaryText = sprintf(
            'Moyenne 7 j : %s g/L (min %s · max %s g/L) · %d valeur(s) basse(s) · %d valeur(s) haute(s) sur %d mesures.',
            number_format($avg, 2, ',', ''),
            number_format($min, 2, ',', ''),
            number_format($max, 2, ',', ''),
            $lowCount,
            $highCount,
            $total
        );

        return [
            'vitals' => $vitals,
            'latest' => $vitals,
            'glycemia_7d' => [
                'points' => $points,
                'avg' => $avg,
                'min' => $min,
                'max' => $max,
                'low_count' => $lowCount,
                'high_count' => $highCount,
                'total' => $total,
                'summary_text' => $summaryText,
            ],
        ];
    }

    private static function deduceMoment(string $hhMm): string
    {
        $hour = (int) substr($hhMm, 0, 2);
        $min = (int) substr($hhMm, 3, 2);
        $totalMin = $hour * 60 + $min;

        if ($totalMin >= 330 && $totalMin < 690) { // 05:30 .. 11:29
            return 'matin';
        }
        if ($totalMin >= 690 && $totalMin < 900) { // 11:30 .. 14:59
            return 'midi';
        }
        if ($totalMin >= 900 && $totalMin < 1230) { // 15:00 .. 20:29
            return 'soir';
        }
        return 'coucher';
    }
}
