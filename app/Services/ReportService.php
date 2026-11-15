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
 * ReportService — Calcul des rapports médicaux imprimables (E23, §3.10 / Phase 4 §2.1)
 * et des tendances 7 jours du tableau de bord administrateur (§2.3).
 */
final class ReportService
{
    /**
     * Résout une période ('7d', '14d', '30d', '90d', 'custom') en bornes locales et UTC.
     *
     * @return array{
     *   period: string,
     *   label: string,
     *   days: int,
     *   from_ymd: string,
     *   to_ymd: string,
     *   from_fr: string,
     *   to_fr: string,
     *   from_utc: string,
     *   to_utc: string
     * }
     */
    public static function resolvePeriod(
        string $period = '30d',
        ?string $fromYmd = null,
        ?string $toYmd = null,
        ?string $referenceNowUtc = null
    ): array {
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $localTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $nowUtc = $referenceNowUtc !== null
            ? new DateTimeImmutable($referenceNowUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);
        $nowLocal = $nowUtc->setTimezone($localTz);

        $cleanPeriod = in_array($period, ['7d', '14d', '30d', '90d', 'custom'], true) ? $period : '30d';

        if (
            $cleanPeriod === 'custom' &&
            $fromYmd !== null &&
            $toYmd !== null &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)
        ) {
            if ($fromYmd > $toYmd) {
                [$fromYmd, $toYmd] = [$toYmd, $fromYmd];
            }
            $startLocal = new DateTimeImmutable($fromYmd . ' 00:00:00', $localTz);
            $endLocal = new DateTimeImmutable($toYmd . ' 23:59:59', $localTz);
            $days = max(1, (int) $startLocal->diff($endLocal)->days + 1);
            $label = 'Du ' . $startLocal->format('d/m/Y') . ' au ' . $endLocal->format('d/m/Y');
        } else {
            $days = match ($cleanPeriod) {
                '7d' => 7,
                '14d' => 14,
                '90d' => 90,
                default => 30,
            };
            if ($cleanPeriod === 'custom') {
                $cleanPeriod = '30d';
            }
            $endLocal = new DateTimeImmutable($nowLocal->format('Y-m-d') . ' 23:59:59', $localTz);
            $startLocal = $endLocal->sub(new DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0, 0);
            $label = "{$days} derniers jours (" . $startLocal->format('d/m/Y') . ' – ' . $endLocal->format('d/m/Y') . ')';
        }

        return [
            'period' => $cleanPeriod,
            'label' => $label,
            'days' => $days,
            'from_ymd' => $startLocal->format('Y-m-d'),
            'to_ymd' => $endLocal->format('Y-m-d'),
            'from_fr' => $startLocal->format('d/m/Y'),
            'to_fr' => $endLocal->format('d/m/Y'),
            'from_utc' => $startLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
            'to_utc' => $endLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Construit l'intégralité du rapport médical sur la période [fromUtc, toUtc].
     * Gère proprement une période vide sans aucune division par zéro ni erreur.
     *
     * @return array<string, mixed>
     */
    public static function buildMedicalReport(
        string $fromUtc,
        string $toUtc,
        int $patientId = 1
    ): array {
        $pdo = Database::connection();

        // 1. Patient info
        $pStmt = $pdo->prepare('SELECT * FROM patients WHERE id = :pid LIMIT 1');
        $pStmt->execute(['pid' => $patientId]);
        $patient = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'id' => 1,
            'first_name' => 'Henri',
            'last_name' => 'Benali',
            'birth_date' => '1942-05-12',
            'diabetes_type' => 'Diabète de type 2',
            'target_glycemia_fasting_min' => 0.80,
            'target_glycemia_fasting_max' => 1.30,
            'target_glycemia_postmeal_max' => 1.80,
            'hypo_threshold_g_l' => 0.70,
            'hyper_threshold_g_l' => 2.50,
        ];

        $hypoThreshold = (float) ($patient['hypo_threshold_g_l'] ?? 0.70);
        $hyperThreshold = (float) ($patient['hyper_threshold_g_l'] ?? 2.50);
        $fastingMin = (float) ($patient['target_glycemia_fasting_min'] ?? 0.80);
        $fastingMax = (float) ($patient['target_glycemia_fasting_max'] ?? 1.30);
        $postMealMax = (float) ($patient['target_glycemia_postmeal_max'] ?? 1.80);

        // 2. Glycemia synthesis
        $glyStmt = $pdo->prepare(
            "SELECT m.id, m.value, m.context, m.measured_at, m.alert_level, m.threshold_level, m.comment,
                    u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM measures m
             INNER JOIN measure_types mt ON mt.id = m.measure_type_id
             LEFT JOIN users u ON u.id = m.recorded_by
             WHERE m.patient_id = :pid
               AND m.deleted_at IS NULL
               AND mt.code = 'glycemia'
               AND m.measured_at BETWEEN :from_utc AND :to_utc
             ORDER BY m.measured_at ASC, m.id ASC"
        );
        $glyStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $glyRows = $glyStmt->fetchAll(PDO::FETCH_ASSOC);

        $glyCount = count($glyRows);
        $glySum = 0.0;
        $glyMin = null;
        $glyMax = null;
        $inTargetCount = 0;
        $hypoCount = 0;
        $hyperCount = 0;

        $byContextBuckets = [
            'fasting' => ['label' => 'À jeun', 'sum' => 0.0, 'count' => 0, 'avg' => null],
            'pre_meal' => ['label' => 'Avant repas', 'sum' => 0.0, 'count' => 0, 'avg' => null],
            'post_meal' => ['label' => 'Après repas (+2h)', 'sum' => 0.0, 'count' => 0, 'avg' => null],
            'bedtime' => ['label' => 'Coucher', 'sum' => 0.0, 'count' => 0, 'avg' => null],
        ];

        $chartPoints = [];
        foreach ($glyRows as $g) {
            $v = (float) $g['value'];
            $ctx = (string) ($g['context'] ?? 'routine');
            $glySum += $v;
            $glyMin = $glyMin === null ? $v : min($glyMin, $v);
            $glyMax = $glyMax === null ? $v : max($glyMax, $v);

            if ($v < $hypoThreshold) {
                $hypoCount++;
            }
            if ($v > $hyperThreshold) {
                $hyperCount++;
            }

            // Target check: threshold_level === 'ok' or within [fastingMin, contextMax]
            $isFastingCtx = in_array($ctx, ['fasting', 'pre_meal', 'a_jeun', 'avant_repas'], true);
            $ctxMax = $isFastingCtx ? $fastingMax : $postMealMax;
            $isOk = ($g['threshold_level'] ?? '') === 'ok' || ($v >= $fastingMin && $v <= $ctxMax);
            if ($isOk) {
                $inTargetCount++;
            }

            if (isset($byContextBuckets[$ctx])) {
                $byContextBuckets[$ctx]['sum'] += $v;
                $byContextBuckets[$ctx]['count']++;
            }

            $chartPoints[] = [
                'at_utc' => (string) $g['measured_at'],
                'label' => ScheduleService::utcToLocalParis((string) $g['measured_at'], 'd/m H:i'),
                'value' => $v,
                'alert_level' => (string) ($g['alert_level'] ?? 'ok'),
            ];
        }

        foreach ($byContextBuckets as $k => $b) {
            if ($b['count'] > 0) {
                $byContextBuckets[$k]['avg'] = round($b['sum'] / $b['count'], 2);
            }
        }

        $glyAvg = $glyCount > 0 ? round($glySum / $glyCount, 2) : null;
        $inTargetPct = $glyCount > 0 ? round(($inTargetCount / $glyCount) * 100.0, 1) : 0.0;

        $glycemiaSvg = self::renderGlycemiaSvg($chartPoints, $fastingMin, $postMealMax, $hypoThreshold, $hyperThreshold);

        // 3. Other constants (blood_pressure, temperature, weight, spo2)
        $otherCodes = [
            'blood_pressure' => ['label' => 'Tension artérielle', 'unit' => 'mmHg', 'decimals' => 0, 'color' => '#5E5CE6'],
            'temperature' => ['label' => 'Température', 'unit' => '°C', 'decimals' => 1, 'color' => '#FF9500'],
            'weight' => ['label' => 'Poids', 'unit' => 'kg', 'decimals' => 1, 'color' => '#00C7BE'],
            'spo2' => ['label' => 'Saturation SpO2', 'unit' => '%', 'decimals' => 0, 'color' => '#007AFF'],
        ];

        $otherStmt = $pdo->prepare(
            "SELECT m.value, m.value2, m.value3, m.measured_at, mt.code
             FROM measures m
             INNER JOIN measure_types mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND m.deleted_at IS NULL
               AND mt.code IN ('blood_pressure', 'temperature', 'weight', 'spo2')
               AND m.measured_at BETWEEN :from_utc AND :to_utc
             ORDER BY m.measured_at ASC, m.id ASC"
        );
        $otherStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $otherRows = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedOther = [];
        foreach (array_keys($otherCodes) as $c) {
            $groupedOther[$c] = [];
        }
        foreach ($otherRows as $or) {
            $c = (string) $or['code'];
            if (isset($groupedOther[$c])) {
                $groupedOther[$c][] = $or;
            }
        }

        $otherVitals = [];
        foreach ($otherCodes as $code => $meta) {
            $rows = $groupedOther[$code];
            $cnt = count($rows);
            if ($cnt === 0) {
                $otherVitals[$code] = [
                    'code' => $code,
                    'label' => $meta['label'],
                    'unit' => $meta['unit'],
                    'decimals' => $meta['decimals'],
                    'count' => 0,
                    'avg_formatted' => '—',
                    'min_formatted' => '—',
                    'max_formatted' => '—',
                    'latest_formatted' => '—',
                    'latest_date' => '—',
                    'sparkline_svg' => self::renderSparklineSvg([], $meta['color']),
                ];
                continue;
            }

            $vals = array_map(static fn(array $r): float => (float) $r['value'], $rows);
            $avg1 = array_sum($vals) / $cnt;
            $min1 = min($vals);
            $max1 = max($vals);
            $last = $rows[$cnt - 1];

            if ($code === 'blood_pressure') {
                $vals2 = array_map(static fn(array $r): float => (float) ($r['value2'] ?? 0), $rows);
                $avg2 = array_sum($vals2) / $cnt;
                $min2 = min($vals2);
                $max2 = max($vals2);
                $avgFmt = round($avg1) . '/' . round($avg2) . ' mmHg';
                $minFmt = round($min1) . '/' . round($min2);
                $maxFmt = round($max1) . '/' . round($max2);
                $lastFmt = round((float) $last['value']) . '/' . round((float) ($last['value2'] ?? 0)) . ' mmHg';
            } else {
                $dec = $meta['decimals'];
                $avgFmt = number_format($avg1, $dec, ',', ' ') . ' ' . $meta['unit'];
                $minFmt = number_format($min1, $dec, ',', ' ');
                $maxFmt = number_format($max1, $dec, ',', ' ');
                $lastFmt = number_format((float) $last['value'], $dec, ',', ' ') . ' ' . $meta['unit'];
            }

            $otherVitals[$code] = [
                'code' => $code,
                'label' => $meta['label'],
                'unit' => $meta['unit'],
                'decimals' => $meta['decimals'],
                'count' => $cnt,
                'avg_formatted' => $avgFmt,
                'min_formatted' => $minFmt,
                'max_formatted' => $maxFmt,
                'latest_formatted' => $lastFmt,
                'latest_date' => ScheduleService::utcToLocalParis((string) $last['measured_at'], 'd/m H:i'),
                'sparkline_svg' => self::renderSparklineSvg($vals, $meta['color']),
            ];
        }

        // 4. Treatment observance (global, per medication, skipped list with reasons, insulin doses & sites)
        $nowUtcStr = gmdate('Y-m-d H:i:s');
        $effectiveToUtc = min($toUtc, $nowUtcStr);

        $intStmt = $pdo->prepare(
            "SELECT mi.id, mi.prescription_id, mi.scheduled_at, mi.status, mi.given_at, mi.given_dose,
                    mi.injection_site, mi.reason, mi.comment,
                    p.unit, p.insulin_kind, p.as_needed,
                    m.id AS medication_id, m.name AS medication_name,
                    u.first_name AS giver_first_name, u.last_name AS giver_last_name
             FROM medication_intakes mi
             INNER JOIN prescriptions p ON p.id = mi.prescription_id
             INNER JOIN medications m ON m.id = p.medication_id
             LEFT JOIN users u ON u.id = mi.given_by
             WHERE mi.patient_id = :pid
               AND mi.deleted_at IS NULL
               AND COALESCE(mi.given_at, mi.scheduled_at) BETWEEN :from_utc AND :to_utc
               AND (mi.scheduled_at <= :eff_to OR mi.status IN ('given', 'skipped'))
             ORDER BY COALESCE(mi.given_at, mi.scheduled_at) ASC, mi.id ASC"
        );
        $intStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
            'eff_to' => $effectiveToUtc,
        ]);
        $intakes = $intStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalScheduled = 0;
        $totalGiven = 0;
        $totalSkipped = 0;
        $byMedication = [];
        $skippedList = [];
        $insulinSummary = [
            'total_injections' => 0,
            'total_units' => 0.0,
            'by_kind' => [
                'lente' => ['label' => 'Insuline lente (basale)', 'count' => 0, 'total_units' => 0.0, 'avg_units' => 0.0],
                'rapide' => ['label' => 'Insuline rapide (bolus)', 'count' => 0, 'total_units' => 0.0, 'avg_units' => 0.0],
            ],
            'by_site' => [],
        ];
        foreach (InsulinService::INJECTION_SITES as $siteCode => $siteLabel) {
            $insulinSummary['by_site'][$siteCode] = [
                'code' => $siteCode,
                'label' => $siteLabel,
                'count' => 0,
            ];
        }

        foreach ($intakes as $in) {
            $medId = (int) $in['medication_id'];
            $medName = (string) $in['medication_name'];
            $status = (string) $in['status'];
            $isAsNeeded = !empty($in['as_needed']);

            if (!isset($byMedication[$medId])) {
                $byMedication[$medId] = [
                    'medication_id' => $medId,
                    'medication_name' => $medName,
                    'unit' => (string) $in['unit'],
                    'as_needed' => $isAsNeeded,
                    'total' => 0,
                    'given' => 0,
                    'skipped' => 0,
                    'rate_pct' => 100.0,
                ];
            }

            if (!$isAsNeeded) {
                $totalScheduled++;
                $byMedication[$medId]['total']++;
            } else {
                $byMedication[$medId]['total']++;
            }

            if ($status === 'given') {
                if (!$isAsNeeded) {
                    $totalGiven++;
                }
                $byMedication[$medId]['given']++;

                if (!empty($in['insulin_kind'])) {
                    $kind = (string) $in['insulin_kind'];
                    $dose = (float) ($in['given_dose'] ?? 0.0);
                    $insulinSummary['total_injections']++;
                    $insulinSummary['total_units'] += $dose;
                    if (isset($insulinSummary['by_kind'][$kind])) {
                        $insulinSummary['by_kind'][$kind]['count']++;
                        $insulinSummary['by_kind'][$kind]['total_units'] += $dose;
                    }
                    $site = (string) ($in['injection_site'] ?? '');
                    if ($site !== '' && isset($insulinSummary['by_site'][$site])) {
                        $insulinSummary['by_site'][$site]['count']++;
                    }
                }
            } elseif ($status === 'skipped') {
                $totalSkipped++;
                $byMedication[$medId]['skipped']++;
                $skippedList[] = [
                    'date_local' => ScheduleService::utcToLocalParis((string) ($in['given_at'] ?: $in['scheduled_at']), 'd/m/Y H:i'),
                    'medication_name' => $medName,
                    'reason' => (string) ($in['reason'] ?: 'Motif non précisé'),
                    'comment' => (string) ($in['comment'] ?? ''),
                    'author' => trim(($in['giver_first_name'] ?? '') . ' ' . ($in['giver_last_name'] ?? '')) ?: 'Équipe',
                ];
            }
        }

        foreach ($byMedication as &$bm) {
            $bm['rate_pct'] = $bm['total'] > 0 ? round(($bm['given'] / $bm['total']) * 100.0, 1) : 100.0;
        }
        unset($bm);

        foreach (['lente', 'rapide'] as $k) {
            $cnt = $insulinSummary['by_kind'][$k]['count'];
            $insulinSummary['by_kind'][$k]['avg_units'] = $cnt > 0
                ? round($insulinSummary['by_kind'][$k]['total_units'] / $cnt, 1)
                : 0.0;
        }

        $globalObservancePct = $totalScheduled > 0
            ? round(($totalGiven / $totalScheduled) * 100.0, 1)
            : 100.0;

        // 5. Nutrition & Meals
        $mealStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt,
                    AVG(portion_eaten) AS avg_portion,
                    AVG(carbs_g) AS avg_carbs,
                    AVG(drink_ml) AS avg_drink
             FROM meals
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND eaten_at BETWEEN :from_utc AND :to_utc"
        );
        $mealStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $mealAgg = $mealStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $mealsCount = (int) ($mealAgg['cnt'] ?? 0);

        $nutrition = [
            'meals_count' => $mealsCount,
            'avg_portion_pct' => $mealsCount > 0 && $mealAgg['avg_portion'] !== null ? round((float) $mealAgg['avg_portion'], 1) : null,
            'avg_carbs_g' => $mealsCount > 0 && $mealAgg['avg_carbs'] !== null ? round((float) $mealAgg['avg_carbs'], 1) : null,
            'avg_drink_ml' => $mealsCount > 0 && $mealAgg['avg_drink'] !== null ? round((float) $mealAgg['avg_drink'], 0) : null,
        ];

        // 6. Notable events (critical measures, falls, incidents, urgent handovers, critical alerts with resolution)
        $critMeasuresStmt = $pdo->prepare(
            "SELECT m.id, m.value, m.value2, m.unit_recorded, m.measured_at, m.comment, m.threshold_level,
                    mt.label AS type_label, mt.unit AS type_unit,
                    u.first_name, u.last_name
             FROM measures m
             INNER JOIN measure_types mt ON mt.id = m.measure_type_id
             LEFT JOIN users u ON u.id = m.recorded_by
             WHERE m.patient_id = :pid
               AND m.deleted_at IS NULL
               AND m.alert_level = 'critical'
               AND m.measured_at BETWEEN :from_utc AND :to_utc
             ORDER BY m.measured_at DESC"
        );
        $critMeasuresStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $criticalMeasures = $critMeasuresStmt->fetchAll(PDO::FETCH_ASSOC);

        $notesStmt = $pdo->prepare(
            "SELECT n.id, n.category, n.priority, n.title, n.content, n.is_handover, n.created_at,
                    u.first_name, u.last_name
             FROM notes n
             LEFT JOIN users u ON u.id = n.recorded_by
             WHERE n.patient_id = :pid
               AND n.deleted_at IS NULL
               AND n.created_at BETWEEN :from_utc AND :to_utc
               AND (n.priority IN ('important', 'urgent') OR n.category = 'medical')
             ORDER BY n.created_at DESC"
        );
        $notesStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $notableNotes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

        $falls = [];
        $incidents = [];
        $urgentHandovers = [];
        foreach ($notableNotes as $nn) {
            $haystack = mb_strtolower((string) ($nn['title'] . ' ' . $nn['content']));
            if (str_contains($haystack, 'chute') || str_contains($haystack, 'gliss')) {
                $falls[] = $nn;
            } elseif (!empty($nn['is_handover'])) {
                $urgentHandovers[] = $nn;
            } else {
                $incidents[] = $nn;
            }
        }

        $alertsStmt = $pdo->prepare(
            "SELECT a.id, a.type, a.level, a.title, a.message, a.status, a.resolution, a.created_at, a.handled_at,
                    u.first_name AS handler_first_name, u.last_name AS handler_last_name
             FROM alerts a
             LEFT JOIN users u ON u.id = a.handled_by
             WHERE a.patient_id = :pid
               AND a.deleted_at IS NULL
               AND a.level = 'critical'
               AND a.created_at BETWEEN :from_utc AND :to_utc
             ORDER BY a.created_at DESC"
        );
        $alertsStmt->execute([
            'pid' => $patientId,
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ]);
        $criticalAlerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'patient' => $patient,
            'glycemia' => [
                'count' => $glyCount,
                'avg' => $glyAvg,
                'min' => $glyMin,
                'max' => $glyMax,
                'in_target_count' => $inTargetCount,
                'in_target_pct' => $inTargetPct,
                'hypo_count' => $hypoCount,
                'hyper_count' => $hyperCount,
                'by_context' => $byContextBuckets,
                'svg_chart' => $glycemiaSvg,
            ],
            'other_vitals' => $otherVitals,
            'observance' => [
                'total_scheduled' => $totalScheduled,
                'total_given' => $totalGiven,
                'total_skipped' => $totalSkipped,
                'global_rate_pct' => $globalObservancePct,
                'by_medication' => array_values($byMedication),
                'skipped_list' => $skippedList,
                'insulin' => $insulinSummary,
            ],
            'nutrition' => $nutrition,
            'events' => [
                'critical_measures' => $criticalMeasures,
                'falls' => $falls,
                'incidents' => $incidents,
                'urgent_handovers' => $urgentHandovers,
                'critical_alerts' => $criticalAlerts,
            ],
        ];
    }

    /**
     * Calcule la section « Tendances 7 jours » de l'accueil administrateur (§2.3) :
     * compare les 7 derniers jours aux 7 jours précédents (J-14..J-7) sur :
     * - glycémie moyenne
     * - temps dans la cible (%)
     * - observance (%)
     * - hypoglycémies (< 0,70 g/L)
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAdmin7DayTrends(int $patientId = 1, ?string $referenceNowUtc = null): array
    {
        $utcTz = new DateTimeZone('UTC');
        $now = $referenceNowUtc !== null
            ? new DateTimeImmutable($referenceNowUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $currTo = $now->format('Y-m-d H:i:s');
        $currFrom = $now->sub(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        $prevTo = $now->sub(new DateInterval('P7D'))->sub(new DateInterval('PT1S'))->format('Y-m-d H:i:s');
        $prevFrom = $now->sub(new DateInterval('P14D'))->format('Y-m-d H:i:s');

        $curr = self::buildMedicalReport($currFrom, $currTo, $patientId);
        $prev = self::buildMedicalReport($prevFrom, $prevTo, $patientId);

        $currGlyAvg = (float) ($curr['glycemia']['avg'] ?? 0.0);
        $prevGlyAvg = (float) ($prev['glycemia']['avg'] ?? 0.0);
        $glyDiff = round($currGlyAvg - $prevGlyAvg, 2);

        $currTir = (float) ($curr['glycemia']['in_target_pct'] ?? 0.0);
        $prevTir = (float) ($prev['glycemia']['in_target_pct'] ?? 0.0);
        $tirDiff = round($currTir - $prevTir, 1);

        $currObs = (float) ($curr['observance']['global_rate_pct'] ?? 100.0);
        $prevObs = (float) ($prev['observance']['global_rate_pct'] ?? 100.0);
        $obsDiff = round($currObs - $prevObs, 1);

        $currHypo = (int) ($curr['glycemia']['hypo_count'] ?? 0);
        $prevHypo = (int) ($prev['glycemia']['hypo_count'] ?? 0);
        $hypoDiff = $currHypo - $prevHypo;

        return [
            'avg_glycemia' => [
                'value' => $currGlyAvg,
                'formatted' => number_format($currGlyAvg, 2, ',', ' '),
                'unit' => 'g/L',
                'decimals' => 2,
                'diff' => $glyDiff,
                'diff_formatted' => ($glyDiff > 0 ? '+' : '') . number_format($glyDiff, 2, ',', ' ') . ' g/L',
                'direction' => $glyDiff > 0.02 ? 'up' : ($glyDiff < -0.02 ? 'down' : 'flat'),
                'trend_tone' => abs($glyDiff) <= 0.15 ? 'ok' : 'warn',
            ],
            'time_in_target' => [
                'value' => $currTir,
                'formatted' => number_format($currTir, 1, ',', ' '),
                'unit' => '%',
                'decimals' => 1,
                'diff' => $tirDiff,
                'diff_formatted' => ($tirDiff > 0 ? '+' : '') . number_format($tirDiff, 1, ',', ' ') . ' pts',
                'direction' => $tirDiff > 0.5 ? 'up' : ($tirDiff < -0.5 ? 'down' : 'flat'),
                'trend_tone' => $tirDiff >= 0 ? 'ok' : 'warn',
            ],
            'observance' => [
                'value' => $currObs,
                'formatted' => number_format($currObs, 1, ',', ' '),
                'unit' => '%',
                'decimals' => 1,
                'diff' => $obsDiff,
                'diff_formatted' => ($obsDiff > 0 ? '+' : '') . number_format($obsDiff, 1, ',', ' ') . ' pts',
                'direction' => $obsDiff > 0.5 ? 'up' : ($obsDiff < -0.5 ? 'down' : 'flat'),
                'trend_tone' => $currObs >= 90.0 ? 'ok' : 'warn',
            ],
            'hypo_count' => [
                'value' => $currHypo,
                'formatted' => (string) $currHypo,
                'unit' => $currHypo > 1 ? 'épisodes' : 'épisode',
                'decimals' => 0,
                'diff' => $hypoDiff,
                'diff_formatted' => ($hypoDiff > 0 ? '+' : '') . $hypoDiff . ' vs J-7',
                'direction' => $hypoDiff > 0 ? 'up' : ($hypoDiff < 0 ? 'down' : 'flat'),
                'trend_tone' => $currHypo === 0 ? 'ok' : ($hypoDiff <= 0 ? 'warn' : 'danger'),
            ],
        ];
    }

    /**
     * Génère un graphique SVG vectoriel net (sans animation, sans attribut style=) de la courbe glycémique
     * avec bandes de seuils (cible verte, seuil hypo 0,70 et seuil hyper 2,50).
     *
     * @param array<int, array{at_utc: string, label: string, value: float, alert_level: string}> $points
     */
    private static function renderGlycemiaSvg(
        array $points,
        float $targetMin,
        float $targetMax,
        float $hypoLine,
        float $hyperLine
    ): string {
        $w = 680;
        $h = 210;
        $padL = 44;
        $padR = 18;
        $padT = 18;
        $padB = 28;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;

        $yMin = 0.40;
        $yMax = 3.00;
        foreach ($points as $p) {
            if ($p['value'] > $yMax) {
                $yMax = ceil($p['value'] * 2) / 2;
            }
        }

        $yToPx = static function (float $val) use ($padT, $plotH, $yMin, $yMax): float {
            $clamped = max($yMin, min($yMax, $val));
            $ratio = ($clamped - $yMin) / max(0.1, $yMax - $yMin);
            return round($padT + $plotH * (1.0 - $ratio), 1);
        };

        $yTargetTop = $yToPx($targetMax);
        $yTargetBottom = $yToPx($targetMin);
        $targetBandH = max(2.0, round($yTargetBottom - $yTargetTop, 1));
        $yHypo = $yToPx($hypoLine);
        $yHyper = $yToPx($hyperLine);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" class="pc-report-svg" role="img" aria-label="Courbe glycémique sur la période">';
        // Background plot area
        $svg .= '<rect x="' . $padL . '" y="' . $padT . '" width="' . $plotW . '" height="' . $plotH . '" rx="8" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>';
        // Target green zone [0.80 .. 1.80]
        $svg .= '<rect x="' . $padL . '" y="' . $yTargetTop . '" width="' . $plotW . '" height="' . $targetBandH . '" fill="#34C759" fill-opacity="0.14"/>';
        // Threshold lines
        $svg .= '<line x1="' . $padL . '" y1="' . $yHypo . '" x2="' . ($w - $padR) . '" y2="' . $yHypo . '" stroke="#FF3B30" stroke-width="1.2" stroke-dasharray="4 4"/>';
        $svg .= '<line x1="' . $padL . '" y1="' . $yHyper . '" x2="' . ($w - $padR) . '" y2="' . $yHyper . '" stroke="#FF9500" stroke-width="1.2" stroke-dasharray="4 4"/>';

        // Y-axis labels
        foreach ([$hypoLine, $targetMin, $targetMax, $hyperLine] as $yVal) {
            $py = $yToPx($yVal);
            $lbl = number_format($yVal, 2, ',', '');
            $svg .= '<text x="' . ($padL - 6) . '" y="' . ($py + 4) . '" text-anchor="end" font-size="10" fill="#64748B" font-family="system-ui, sans-serif">' . $lbl . '</text>';
        }

        $cnt = count($points);
        if ($cnt === 0) {
            $svg .= '<text x="' . ($w / 2) . '" y="' . ($h / 2) . '" text-anchor="middle" font-size="12" fill="#64748B" font-family="system-ui, sans-serif">Aucune mesure de glycémie sur cette période</text>';
            $svg .= '</svg>';
            return $svg;
        }

        $coords = [];
        foreach ($points as $i => $pt) {
            $px = $cnt === 1
                ? $padL + ($plotW / 2)
                : round($padL + ($i / ($cnt - 1)) * $plotW, 1);
            $py = $yToPx($pt['value']);
            $coords[] = $px . ',' . $py;
        }

        $svg .= '<polyline fill="none" stroke="#FF2D55" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" points="' . implode(' ', $coords) . '"/>';

        foreach ($points as $i => $pt) {
            $px = $cnt === 1
                ? $padL + ($plotW / 2)
                : round($padL + ($i / ($cnt - 1)) * $plotW, 1);
            $py = $yToPx($pt['value']);
            $dotColor = $pt['alert_level'] === 'critical' ? '#FF3B30' : ($pt['alert_level'] === 'warn' ? '#FF9500' : '#34C759');
            $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="3.2" fill="' . $dotColor . '" stroke="#FFFFFF" stroke-width="1"/>';
        }

        // First & last date labels on X axis
        $firstLbl = htmlspecialchars($points[0]['label'], ENT_QUOTES, 'UTF-8');
        $lastLbl = htmlspecialchars($points[$cnt - 1]['label'], ENT_QUOTES, 'UTF-8');
        $svg .= '<text x="' . $padL . '" y="' . ($h - 8) . '" text-anchor="start" font-size="10" fill="#64748B" font-family="system-ui, sans-serif">' . $firstLbl . '</text>';
        $svg .= '<text x="' . ($w - $padR) . '" y="' . ($h - 8) . '" text-anchor="end" font-size="10" fill="#64748B" font-family="system-ui, sans-serif">' . $lastLbl . '</text>';

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Génère une mini-courbe SVG (sparkline) sans animation ni attribut style=.
     *
     * @param array<int, float> $values
     */
    private static function renderSparklineSvg(array $values, string $strokeColor = '#5E5CE6'): string
    {
        $w = 180;
        $h = 44;
        $cnt = count($values);
        if ($cnt === 0) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" class="pc-sparkline-svg" aria-hidden="true">'
                . '<line x1="8" y1="22" x2="172" y2="22" stroke="#CBD5E1" stroke-width="1.5" stroke-dasharray="3 3"/>'
                . '</svg>';
        }

        $min = min($values);
        $max = max($values);
        $range = max(0.5, $max - $min);

        $pts = [];
        foreach ($values as $i => $v) {
            $x = $cnt === 1 ? 90.0 : round(8 + ($i / ($cnt - 1)) * ($w - 16), 1);
            $y = round(8 + (1.0 - (($v - $min) / $range)) * ($h - 16), 1);
            $pts[] = $x . ',' . $y;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" class="pc-sparkline-svg" aria-hidden="true">'
            . '<polyline fill="none" stroke="' . $strokeColor . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . implode(' ', $pts) . '"/>'
            . '</svg>';
    }
}
