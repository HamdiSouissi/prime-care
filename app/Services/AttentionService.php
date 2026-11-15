<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Computes real-time "points d'attention" displayed to the administrator and on-duty caregiver:
 * - Unresolved critical measurement (not followed by an 'ok' measurement of the same type)
 * - Medication intake late by > 30 minutes
 * - Expected measurement late
 * - Unread urgent handover or note
 */
final class AttentionService
{
    /**
     * Get the current list of attention items and headline summary.
     *
     * @return array{
     *   count: int,
     *   headline: string,
     *   status_level: string,
     *   status_icon: string,
     *   items: array<int, array{
     *     type: string,
     *     severity: string,
     *     icon: string,
     *     title: string,
     *     subtitle: string,
     *     time_local: string,
     *     action_url: string,
     *     action_label: string
     *   }>
     * }
     */
    public static function getCurrentAttentionPoints(
        ?int $userId = null,
        int $patientId = 1,
        ?string $referenceUtc = null
    ): array {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $refDt = $referenceUtc !== null
            ? new DateTimeImmutable($referenceUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);

        $nowUtc = $refDt->format('Y-m-d H:i:s');
        $last12hUtc = $refDt->modify('-12 hours')->format('Y-m-d H:i:s');
        $lateCutoffUtc = $refDt->modify('-30 minutes')->format('Y-m-d H:i:s');
        $todayStartUtc = ScheduleService::localParisToUtc(
            ScheduleService::utcToLocalParis($nowUtc, 'Y-m-d'),
            '00:00'
        );

        $items = [];

        // 1. Critical measurement in the last 12h not followed by a normal ('ok') measurement of the same type
        $critStmt = $pdo->prepare(
            'SELECT m.*, mt.code, mt.label AS type_label, mt.unit
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND m.alert_level = \'critical\'
               AND m.measured_at BETWEEN :from_utc AND :to_utc
               AND m.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM `measures` m2
                   WHERE m2.patient_id = m.patient_id
                     AND m2.measure_type_id = m.measure_type_id
                     AND m2.measured_at > m.measured_at
                     AND m2.alert_level = \'ok\'
                     AND m2.deleted_at IS NULL
               )
             ORDER BY m.measured_at DESC
             LIMIT 3'
        );
        $critStmt->execute(['pid' => $patientId, 'from_utc' => $last12hUtc, 'to_utc' => $nowUtc]);
        foreach ($critStmt->fetchAll(PDO::FETCH_ASSOC) as $cRow) {
            $valStr = str_replace('.', ',', (string) $cRow['value']) . ' ' . (string) $cRow['unit'];
            $items[] = [
                'type' => 'critical_measure',
                'severity' => 'critical',
                'icon' => 'alert-octagon',
                'title' => sprintf('%s critique non recontrôlée (%s)', (string) $cRow['type_label'], $valStr),
                'subtitle' => 'Aucun contrôle revenu dans la cible depuis cette mesure.',
                'time_local' => ScheduleService::utcToLocalParis((string) $cRow['measured_at'], 'H:i'),
                'action_url' => '/health/' . urlencode((string) $cRow['code']),
                'action_label' => 'Contrôler',
            ];
        }

        // 2. Medication intakes late by > 30 minutes today
        $lateIntakeStmt = $pdo->prepare(
            'SELECT mi.id, mi.scheduled_at, p.dose, p.unit, m.name AS medication_name
             FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             INNER JOIN `medications` m ON m.id = p.medication_id
             WHERE mi.patient_id = :pid
               AND mi.status = \'pending\'
               AND mi.scheduled_at BETWEEN :today_start AND :late_cutoff
               AND mi.deleted_at IS NULL
             ORDER BY mi.scheduled_at ASC
             LIMIT 4'
        );
        $lateIntakeStmt->execute([
            'pid' => $patientId,
            'today_start' => $todayStartUtc,
            'late_cutoff' => $lateCutoffUtc,
        ]);
        foreach ($lateIntakeStmt->fetchAll(PDO::FETCH_ASSOC) as $li) {
            $schedLocal = ScheduleService::utcToLocalParis((string) $li['scheduled_at'], 'H:i');
            $items[] = [
                'type' => 'late_intake',
                'severity' => 'critical',
                'icon' => 'pill',
                'title' => sprintf('Prise en retard : %s (%s %s)', (string) $li['medication_name'], str_replace('.', ',', (string) $li['dose']), (string) $li['unit']),
                'subtitle' => sprintf('Prévue à %s (retard > 30 min)', $schedLocal),
                'time_local' => $schedLocal,
                'action_url' => '/today',
                'action_label' => 'Valider',
            ];
        }

        // 3. Expected measurements missing/late today
        $lateExpStmt = $pdo->prepare(
            'SELECT me.id, me.scheduled_at, mt.code, mt.label AS type_label, ms.label AS schedule_label
             FROM `measure_expectations` me
             INNER JOIN `measure_types` mt ON mt.id = me.measure_type_id
             INNER JOIN `measure_schedules` ms ON ms.id = me.measure_schedule_id
             WHERE me.patient_id = :pid
               AND me.measure_id IS NULL
               AND me.scheduled_at BETWEEN :today_start AND :late_cutoff
               AND me.deleted_at IS NULL
             ORDER BY me.scheduled_at ASC
             LIMIT 3'
        );
        $lateExpStmt->execute([
            'pid' => $patientId,
            'today_start' => $todayStartUtc,
            'late_cutoff' => $lateCutoffUtc,
        ]);
        foreach ($lateExpStmt->fetchAll(PDO::FETCH_ASSOC) as $le) {
            $schedLocal = ScheduleService::utcToLocalParis((string) $le['scheduled_at'], 'H:i');
            $items[] = [
                'type' => 'late_measure',
                'severity' => 'warn',
                'icon' => 'activity',
                'title' => sprintf('Mesure attendue manquante : %s', (string) $le['type_label']),
                'subtitle' => sprintf('%s prévue à %s', (string) ($le['schedule_label'] ?: $le['type_label']), $schedLocal),
                'time_local' => $schedLocal,
                'action_url' => '/health/' . urlencode((string) $le['code']),
                'action_label' => 'Saisir',
            ];
        }

        // 4. Unread urgent note or handover
        if ($userId !== null && $userId > 0) {
            $unreadStmt = $pdo->prepare(
                'SELECT n.id, n.title, n.content, n.is_handover, n.priority, n.created_at, u.first_name
                 FROM `notes` n
                 INNER JOIN `users` u ON u.id = n.recorded_by
                 LEFT JOIN `note_reads` nr ON nr.note_id = n.id AND nr.user_id = :uid
                 WHERE n.patient_id = :pid
                   AND n.deleted_at IS NULL
                   AND n.recorded_by != :uid2
                   AND nr.id IS NULL
                   AND (n.is_handover = 1 OR n.priority = \'urgent\')
                 ORDER BY n.created_at DESC
                 LIMIT 2'
            );
            $unreadStmt->execute(['uid' => $userId, 'pid' => $patientId, 'uid2' => $userId]);
            foreach ($unreadStmt->fetchAll(PDO::FETCH_ASSOC) as $un) {
                $isHandover = (int) $un['is_handover'] === 1;
                $items[] = [
                    'type' => 'unread_handover',
                    'severity' => (string) $un['priority'] === 'urgent' ? 'critical' : 'warn',
                    'icon' => 'message-square-text',
                    'title' => $isHandover
                        ? sprintf('Transmission non lue de %s', (string) $un['first_name'])
                        : sprintf('Note urgente non lue de %s', (string) $un['first_name']),
                    'subtitle' => mb_strimwidth((string) $un['content'], 0, 80, '…'),
                    'time_local' => ScheduleService::utcToLocalParis((string) $un['created_at'], 'H:i'),
                    'action_url' => '/journal?filter=notes',
                    'action_label' => 'Lire',
                ];
            }
        }

        $count = count($items);
        $hasCritical = false;
        foreach ($items as &$it) {
            $it['link'] = $it['action_url'] ?? '/today';
            $it['time'] = $it['time_local'] ?? '';
            if ($it['severity'] === 'critical') {
                $hasCritical = true;
            }
        }
        unset($it);

        if ($count === 0) {
            $headline = 'Tout va bien';
            $statusLevel = 'ok';
            $statusIcon = 'check-circle';
        } elseif ($count === 1) {
            $headline = '1 point d’attention';
            $statusLevel = $hasCritical ? 'critical' : 'warn';
            $statusIcon = $hasCritical ? 'alert-octagon' : 'alert-triangle';
        } else {
            $headline = sprintf('%d points d’attention', $count);
            $statusLevel = $hasCritical ? 'critical' : 'warn';
            $statusIcon = $hasCritical ? 'alert-octagon' : 'alert-triangle';
        }

        return [
            'count' => $count,
            'headline' => $headline,
            'status_level' => $statusLevel,
            'status_icon' => $statusIcon,
            'items' => $items,
        ];
    }
}
