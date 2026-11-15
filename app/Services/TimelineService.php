<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Produces the unified daily timeline (measures, medication intakes, meals, notes/handovers, care checks)
 * sorted chronologically, filterable by type, and paginated, as well as the structured caregiver shift timeline.
 */
final class TimelineService
{
    /**
     * Get the unified daily feed for a local Europe/Paris date (YYYY-MM-DD).
     *
     * @return array{
     *   date: string,
     *   type: string,
     *   page: int,
     *   per_page: int,
     *   total: int,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public static function getDailyFeed(
        string $localDate,
        string $filterType = 'all',
        int $page = 1,
        int $perPage = 25,
        int $patientId = 1,
        ?int $currentUserId = null
    ): array {
        $pdo = Database::connection();
        $fromUtc = ScheduleService::localParisToUtc($localDate, '00:00');
        $toUtc = ScheduleService::localParisToUtc($localDate, '23:59');

        $cleanType = in_array($filterType, ['all', 'measures', 'intakes', 'meals', 'notes', 'care'], true)
            ? $filterType
            : 'all';

        $items = [];

        // 1. Measures
        if ($cleanType === 'all' || $cleanType === 'measures') {
            $mStmt = $pdo->prepare(
                'SELECT m.*,
                        mt.code AS type_code,
                        mt.label AS type_label,
                        mt.unit,
                        mt.secondary_unit,
                        mt.tertiary_unit,
                        mt.icon_name,
                        mt.decimals,
                        u.first_name AS author_first_name,
                        u.last_name AS author_last_name
                 FROM `measures` m
                 INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
                 INNER JOIN `users` u ON u.id = m.recorded_by
                 WHERE m.patient_id = :pid
                   AND m.measured_at BETWEEN :from_utc AND :to_utc
                   AND m.deleted_at IS NULL'
            );
            $mStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
            foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $decimals = (int) ($row['decimals'] ?? 1);
                $valDisplay = number_format((float) $row['value'], $decimals, ',', ' ');
                if ($row['value2'] !== null && (string) $row['type_code'] === 'blood_pressure') {
                    $valDisplay = sprintf('%d/%d', (int) round((float) $row['value']), (int) round((float) $row['value2']));
                }

                $eval = ThresholdService::evaluate(
                    [
                        'code' => $row['type_code'],
                        'crit_low' => null,
                        'low' => null,
                        'high' => null,
                        'crit_high' => null,
                    ],
                    (float) $row['value'],
                    (string) $row['context'],
                    $row['value2'] !== null ? (float) $row['value2'] : null
                );

                $statusBadgeClass = match ((string) $row['alert_level']) {
                    'critical' => 'pc-status-pill--critical',
                    'warn' => 'pc-status-pill--warn',
                    default => 'pc-status-pill--ok',
                };
                $statusIcon = match ((string) $row['alert_level']) {
                    'critical' => 'alert-octagon',
                    'warn' => 'alert-triangle',
                    default => 'check-circle',
                };
                $statusLabel = match ((string) $row['threshold_level']) {
                    'crit_low' => (string) $row['type_code'] === 'glycemia' ? 'Hypoglycémie critique' : 'Critique bas',
                    'low' => 'Bas',
                    'high' => 'Au-dessus de la cible',
                    'crit_high' => (string) $row['type_code'] === 'glycemia' ? 'Hyperglycémie critique' : 'Critique élevé',
                    default => 'Dans la cible',
                };

                $items[] = [
                    'kind' => 'measures',
                    'id' => (int) $row['id'],
                    'timestamp_utc' => (string) $row['measured_at'],
                    'time_local' => ScheduleService::utcToLocalParis((string) $row['measured_at'], 'H:i'),
                    'module_class' => 'vitals',
                    'icon' => (string) ($row['icon_name'] ?: 'activity'),
                    'title' => (string) $row['type_label'],
                    'value_display' => $valDisplay . ' ' . (string) $row['unit'],
                    'subtitle' => self::contextLabel((string) $row['context']),
                    'comment' => (string) ($row['comment'] ?? ''),
                    'photo_path' => $row['photo_path'],
                    'photo_thumb_path' => $row['photo_thumb_path'],
                    'status_label' => $statusLabel,
                    'status_icon' => $statusIcon,
                    'status_badge_class' => $statusBadgeClass,
                    'alert_level' => (string) $row['alert_level'],
                    'author_name' => trim($row['author_first_name'] . ' ' . $row['author_last_name']),
                    'recorded_by' => (int) $row['recorded_by'],
                    'created_at' => (string) $row['created_at'],
                    'detail_url' => '/health/' . urlencode((string) $row['type_code']),
                ];
            }
        }

        // 2. Medication intakes (given or skipped, or due today)
        if ($cleanType === 'all' || $cleanType === 'intakes') {
            $iStmt = $pdo->prepare(
                'SELECT mi.*,
                        p.dose AS rx_dose,
                        p.unit AS rx_unit,
                        p.insulin_kind,
                        p.dose_mode,
                        m.name AS medication_name,
                        m.form AS medication_form,
                        u.first_name AS author_first_name,
                        u.last_name AS author_last_name
                 FROM `medication_intakes` mi
                 INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
                 INNER JOIN `medications` m ON m.id = p.medication_id
                 LEFT JOIN `users` u ON u.id = mi.given_by
                 WHERE mi.patient_id = :pid
                   AND COALESCE(mi.given_at, mi.scheduled_at) BETWEEN :from_utc AND :to_utc
                   AND mi.status IN (\'given\', \'skipped\')
                   AND mi.deleted_at IS NULL'
            );
            $iStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
            foreach ($iStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $tsUtc = (string) ($row['given_at'] ?: $row['scheduled_at']);
                $disp = IntakeService::computeDisplayStatus($row);
                $doseVal = $row['given_dose'] !== null ? (float) $row['given_dose'] : (float) $row['rx_dose'];
                $doseStr = str_replace('.', ',', (string) (floor($doseVal) == $doseVal ? (int) $doseVal : $doseVal)) . ' ' . (string) $row['rx_unit'];

                $subtitleParts = [];
                if (!empty($row['injection_site']) && isset(InsulinService::INJECTION_SITES[(string) $row['injection_site']])) {
                    $subtitleParts[] = 'Site : ' . InsulinService::INJECTION_SITES[(string) $row['injection_site']];
                }
                if (!empty($row['reason'])) {
                    $subtitleParts[] = 'Motif : ' . (string) $row['reason'];
                }

                $items[] = [
                    'kind' => 'intakes',
                    'id' => (int) $row['id'],
                    'timestamp_utc' => $tsUtc,
                    'time_local' => ScheduleService::utcToLocalParis($tsUtc, 'H:i'),
                    'module_class' => 'meds',
                    'icon' => $row['insulin_kind'] !== null ? 'syringe' : 'pill',
                    'title' => (string) $row['medication_name'],
                    'value_display' => $doseStr,
                    'subtitle' => implode(' · ', $subtitleParts),
                    'comment' => (string) ($row['comment'] ?? ''),
                    'photo_path' => null,
                    'photo_thumb_path' => null,
                    'status_label' => $disp['label'],
                    'status_icon' => $disp['icon'],
                    'status_badge_class' => $disp['badge_class'],
                    'alert_level' => $row['status'] === 'given' ? 'ok' : 'warn',
                    'author_name' => trim(($row['author_first_name'] ?? '') . ' ' . ($row['author_last_name'] ?? '')) ?: 'Équipe',
                    'recorded_by' => (int) ($row['given_by'] ?? 0),
                    'created_at' => (string) $row['created_at'],
                    'detail_url' => '/treatments/history',
                ];
            }
        }

        // 3. Meals
        if ($cleanType === 'all' || $cleanType === 'meals') {
            $mealStmt = $pdo->prepare(
                'SELECT ml.*,
                        u.first_name AS author_first_name,
                        u.last_name AS author_last_name
                 FROM `meals` ml
                 INNER JOIN `users` u ON u.id = ml.recorded_by
                 WHERE ml.patient_id = :pid
                   AND ml.eaten_at BETWEEN :from_utc AND :to_utc
                   AND ml.deleted_at IS NULL'
            );
            $mealStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
            foreach ($mealStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pStmt = $pdo->prepare(
                    'SELECT `kind`, `photo_path`, `thumb_path`
                     FROM `meal_photos`
                     WHERE `meal_id` = :mid AND `deleted_at` IS NULL
                     ORDER BY `sort_order` ASC'
                );
                $pStmt->execute(['mid' => (int) $row['id']]);
                $photos = $pStmt->fetchAll(PDO::FETCH_ASSOC);

                $portion = (int) $row['portion_eaten'];
                $mealTypeLabel = MealService::MEAL_TYPES[(string) $row['meal_type']] ?? 'Repas';

                $items[] = [
                    'kind' => 'meals',
                    'id' => (int) $row['id'],
                    'timestamp_utc' => (string) $row['eaten_at'],
                    'time_local' => ScheduleService::utcToLocalParis((string) $row['eaten_at'], 'H:i'),
                    'module_class' => 'meals',
                    'icon' => 'utensils',
                    'title' => $mealTypeLabel,
                    'value_display' => sprintf('Portion : %d %%', $portion),
                    'subtitle' => (string) $row['description'],
                    'comment' => (string) ($row['comment'] ?? ''),
                    'photo_path' => $photos[0]['photo_path'] ?? null,
                    'photo_thumb_path' => $photos[0]['thumb_path'] ?? null,
                    'photos' => $photos,
                    'status_label' => MealService::PORTIONS[$portion] ?? ($portion . ' %'),
                    'status_icon' => $portion >= 50 ? 'check-circle' : 'alert-triangle',
                    'status_badge_class' => $portion >= 50 ? 'pc-status-pill--ok' : 'pc-status-pill--warn',
                    'alert_level' => $portion >= 50 ? 'ok' : 'warn',
                    'author_name' => trim($row['author_first_name'] . ' ' . $row['author_last_name']),
                    'recorded_by' => (int) $row['recorded_by'],
                    'created_at' => (string) $row['created_at'],
                    'detail_url' => '/meals/' . (int) $row['id'],
                ];
            }
        }

        // 4. Notes & Handovers
        if ($cleanType === 'all' || $cleanType === 'notes') {
            $nStmt = $pdo->prepare(
                'SELECT n.*,
                        u.first_name AS author_first_name,
                        u.last_name AS author_last_name
                 FROM `notes` n
                 INNER JOIN `users` u ON u.id = n.recorded_by
                 WHERE n.patient_id = :pid
                   AND n.created_at BETWEEN :from_utc AND :to_utc
                   AND n.deleted_at IS NULL'
            );
            $nStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
            foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $isHandover = (int) $row['is_handover'] === 1;
                $prio = (string) $row['priority'];
                $prioLabel = match ($prio) {
                    'urgent' => 'Urgent',
                    'important' => 'Important',
                    default => $isHandover ? 'Transmission' : 'Observation',
                };
                $prioBadge = match ($prio) {
                    'urgent' => 'pc-status-pill--critical',
                    'important' => 'pc-status-pill--warn',
                    default => 'pc-status-pill--info',
                };
                $prioIcon = match ($prio) {
                    'urgent' => 'alert-octagon',
                    'important' => 'alert-triangle',
                    default => $isHandover ? 'message-square-text' : 'notebook-pen',
                };

                // Read receipts
                $rStmt = $pdo->prepare(
                    'SELECT nr.user_id, u.first_name
                     FROM `note_reads` nr
                     INNER JOIN `users` u ON u.id = nr.user_id
                     WHERE nr.note_id = :nid'
                );
                $rStmt->execute(['nid' => (int) $row['id']]);
                $reads = $rStmt->fetchAll(PDO::FETCH_ASSOC);
                $isReadByCurrent = false;
                $readerNames = [];
                foreach ($reads as $rd) {
                    $readerNames[] = (string) $rd['first_name'];
                    if ($currentUserId !== null && (int) $rd['user_id'] === $currentUserId) {
                        $isReadByCurrent = true;
                    }
                }

                $items[] = [
                    'kind' => 'notes',
                    'id' => (int) $row['id'],
                    'timestamp_utc' => (string) $row['created_at'],
                    'time_local' => ScheduleService::utcToLocalParis((string) $row['created_at'], 'H:i'),
                    'module_class' => 'notes',
                    'icon' => $isHandover ? 'message-square-text' : 'notebook-pen',
                    'title' => $isHandover
                        ? 'Transmission de fin de poste'
                        : ((string) ($row['title'] ?: self::noteCategoryLabel((string) $row['category']))),
                    'value_display' => $prioLabel,
                    'subtitle' => (string) $row['content'],
                    'comment' => '',
                    'is_handover' => $isHandover,
                    'handover_summary' => is_string($row['handover_summary'] ?? null)
                        ? (json_decode((string) $row['handover_summary'], true) ?: [])
                        : [],
                    'is_read_by_current' => $isReadByCurrent || ((int) $row['recorded_by'] === (int) $currentUserId),
                    'reader_names' => $readerNames,
                    'photo_path' => $row['photo_path'],
                    'photo_thumb_path' => $row['photo_thumb_path'],
                    'status_label' => $prioLabel,
                    'status_icon' => $prioIcon,
                    'status_badge_class' => $prioBadge,
                    'alert_level' => $prio === 'urgent' ? 'critical' : ($prio === 'important' ? 'warn' : 'ok'),
                    'author_name' => trim($row['author_first_name'] . ' ' . $row['author_last_name']),
                    'recorded_by' => (int) $row['recorded_by'],
                    'created_at' => (string) $row['created_at'],
                    'detail_url' => '/journal?filter=notes',
                ];
            }
        }

        // 5. Care checks
        if ($cleanType === 'all' || $cleanType === 'care') {
            $cStmt = $pdo->prepare(
                'SELECT cc.*,
                        ci.label AS care_label,
                        ci.icon_name,
                        u.first_name AS author_first_name,
                        u.last_name AS author_last_name
                 FROM `care_checks` cc
                 INNER JOIN `care_items` ci ON ci.id = cc.care_item_id
                 INNER JOIN `users` u ON u.id = cc.performed_by
                 WHERE cc.patient_id = :pid
                   AND cc.checked_at BETWEEN :from_utc AND :to_utc
                   AND cc.deleted_at IS NULL'
            );
            $cStmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'kind' => 'care',
                    'id' => (int) $row['id'],
                    'timestamp_utc' => (string) $row['checked_at'],
                    'time_local' => ScheduleService::utcToLocalParis((string) $row['checked_at'], 'H:i'),
                    'module_class' => 'notes',
                    'icon' => (string) ($row['icon_name'] ?: 'check-circle'),
                    'title' => (string) $row['care_label'],
                    'value_display' => 'Réalisé',
                    'subtitle' => (string) ($row['comment'] ?? 'Soin quotidien effectué'),
                    'comment' => '',
                    'photo_path' => null,
                    'photo_thumb_path' => null,
                    'status_label' => 'Fait',
                    'status_icon' => 'check-circle',
                    'status_badge_class' => 'pc-status-pill--ok',
                    'alert_level' => 'ok',
                    'author_name' => trim($row['author_first_name'] . ' ' . $row['author_last_name']),
                    'recorded_by' => (int) $row['performed_by'],
                    'created_at' => (string) $row['created_at'],
                    'detail_url' => '/journal?filter=care',
                ];
            }
        }

        // Sort descending by timestamp_utc
        usort($items, static fn(array $a, array $b): int => strcmp($b['timestamp_utc'], $a['timestamp_utc']));

        $total = count($items);
        $safePage = max(1, $page);
        $safePerPage = max(5, min(100, $perPage));
        $offset = ($safePage - 1) * $safePerPage;
        $pagedItems = array_slice($items, $offset, $safePerPage);

        return [
            'date' => $localDate,
            'type' => $cleanType,
            'page' => $safePage,
            'per_page' => $safePerPage,
            'total' => $total,
            'has_more' => ($offset + count($pagedItems)) < $total,
            'items' => $pagedItems,
        ];
    }

    public static function contextLabel(string $context): string
    {
        return match ($context) {
            'fasting', 'a_jeun' => 'À jeun (réveil)',
            'pre_meal', 'avant_repas' => 'Avant repas',
            'post_meal', 'apres_repas' => 'Après repas (+2h)',
            'bedtime', 'coucher' => 'Au coucher',
            'night', 'nuit' => 'Contrôle de nuit',
            'symptom', 'symptome' => 'Symptôme / malaise',
            default => 'Contrôle régulier',
        };
    }

    public static function noteCategoryLabel(string $category): string
    {
        return match ($category) {
            'medical' => 'Observation médicale',
            'behavior' => 'Humeur & comportement',
            'hygiene' => 'Hygiène & confort',
            'family' => 'Information famille',
            'supply' => 'Stock & pharmacie',
            'handover' => 'Transmission de fin de poste',
            default => 'Note générale',
        };
    }
}
