<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * AttentionService (unifié Phase 3) :
 * S'appuie sur les alertes ouvertes en base (`alerts` au statut `new` ou `seen`) comme source unique de vérité,
 * complétées par les transmissions de fin de poste non lues par l'utilisateur courant.
 */
final class AttentionService
{
    /**
     * Get the current list of attention items and headline summary from open `alerts`.
     *
     * @return array{
     *   count: int,
     *   headline: string,
     *   status_level: string,
     *   status_icon: string,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public static function getCurrentAttentionPoints(
        ?int $userId = null,
        int $patientId = 1,
        ?string $referenceUtc = null
    ): array {
        $pdo = Database::connection();

        // Synchronise les alertes périodiques ouvertes pour garantir la fraîcheur
        AlertService::scanPeriodicAlerts($patientId, $referenceUtc);

        $openAlerts = AlertService::listAlerts('open', $patientId, 10);
        $items = [];

        foreach ($openAlerts as $al) {
            $level = (string) ($al['level'] ?? 'warning');
            $type = (string) ($al['type'] ?? 'vital_out_of_range');
            $severity = match ($level) {
                'critical' => 'critical',
                'warning' => 'warn',
                default => 'info',
            };
            $icon = match ($type) {
                'vital_critical', 'vital_out_of_range' => 'heart-pulse',
                'intake_late' => 'pill',
                'measure_late' => 'activity',
                'attendance_missing', 'attendance_pending' => 'clock',
                'shift_uncovered' => 'calendar-days',
                default => 'alert-triangle',
            };
            $actionLabel = match ($type) {
                'vital_critical', 'vital_out_of_range' => 'Contrôler',
                'intake_late' => 'Valider',
                'measure_late' => 'Saisir',
                'shift_uncovered' => 'Affecter',
                default => 'Voir',
            };

            $items[] = [
                'alert_id' => (int) $al['id'],
                'type' => $type,
                'severity' => $severity,
                'icon' => $icon,
                'title' => (string) $al['title'],
                'subtitle' => mb_strimwidth((string) $al['message'], 0, 95, '…'),
                'time_local' => ScheduleService::utcToLocalParis((string) $al['created_at'], 'H:i'),
                'action_url' => (string) ($al['target_url'] ?: '/alerts'),
                'action_label' => $actionLabel,
            ];
        }

        // Compléter avec les transmissions urgentes/récentes non lues par l'utilisateur connecté
        if ($userId !== null && $userId > 0) {
            $unreadStmt = $pdo->prepare(
                "SELECT n.id, n.title, n.content, n.is_handover, n.priority, n.created_at, n.recorded_by, u.first_name
                 FROM notes n
                 INNER JOIN users u ON u.id = n.recorded_by
                 LEFT JOIN note_reads nr ON nr.note_id = n.id AND nr.user_id = :uid
                 WHERE n.patient_id = :pid
                   AND n.deleted_at IS NULL
                   AND n.recorded_by != :uid2
                   AND nr.id IS NULL
                   AND (n.is_handover = 1 OR n.priority = 'urgent')
                 ORDER BY n.created_at DESC"
            );
            $unreadStmt->execute(['uid' => $userId, 'pid' => $patientId, 'uid2' => $userId]);
            $groupedUnread = [];
            foreach ($unreadStmt->fetchAll(PDO::FETCH_ASSOC) as $un) {
                $isHandover = (int) $un['is_handover'] === 1;
                $groupKey = ((int) $un['recorded_by']) . ':' . ($isHandover ? 'handover' : 'urgent_note');
                if (!isset($groupedUnread[$groupKey])) {
                    $groupedUnread[$groupKey] = [
                        'count' => 0,
                        'is_handover' => $isHandover,
                        'first_name' => (string) $un['first_name'],
                        'latest_content' => (string) $un['content'],
                        'latest_created_at' => (string) $un['created_at'],
                        'has_urgent' => false,
                    ];
                }
                $groupedUnread[$groupKey]['count']++;
                if ((string) $un['priority'] === 'urgent') {
                    $groupedUnread[$groupKey]['has_urgent'] = true;
                }
            }

            foreach ($groupedUnread as $grp) {
                $nCnt = $grp['count'];
                $title = $grp['is_handover']
                    ? ($nCnt > 1 ? sprintf('%d transmissions non lues de %s', $nCnt, $grp['first_name']) : sprintf('Transmission non lue de %s', $grp['first_name']))
                    : ($nCnt > 1 ? sprintf('%d notes urgentes non lues de %s', $nCnt, $grp['first_name']) : sprintf('Note urgente non lue de %s', $grp['first_name']));

                $items[] = [
                    'type' => 'unread_handover',
                    'severity' => $grp['has_urgent'] ? 'critical' : 'warn',
                    'icon' => 'notebook-pen',
                    'title' => $title,
                    'subtitle' => mb_strimwidth($grp['latest_content'], 0, 80, '…'),
                    'time_local' => ScheduleService::utcToLocalParis($grp['latest_created_at'], 'H:i'),
                    'action_url' => '/journal?filter=notes',
                    'action_label' => 'Lire',
                ];
            }
        }

        $count = count($items);
        $hasCritical = false;
        foreach ($items as &$it) {
            $it['link'] = $it['action_url'] ?? '/alerts';
            $it['time'] = $it['time_local'] ?? '';
            if ($it['severity'] === 'critical') {
                $hasCritical = true;
            }
        }
        unset($it);

        if ($count === 0) {
            $headline = 'Tout va bien aujourd’hui';
            $statusLevel = 'ok';
            $statusIcon = 'check-circle';
        } elseif ($count === 1) {
            $headline = '1 point d’attention';
            $statusLevel = $hasCritical ? 'critical' : 'warn';
            $statusIcon = $hasCritical ? 'shield-alert' : 'alert-triangle';
        } else {
            $headline = sprintf('%d points d’attention', $count);
            $statusLevel = $hasCritical ? 'critical' : 'warn';
            $statusIcon = $hasCritical ? 'shield-alert' : 'alert-triangle';
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
