<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Services\Channels\SmsChannel;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

/**
 * AlertService — Moteur d'alertes médicales et d'organisation (§3.8) :
 * - 7 règles de déclenchement (immédiates à la saisie + scan périodique cron)
 * - Déduplication stricte par `dedupe_key` tant que l'alerte est ouverte (`new` ou `seen`)
 * - Escalade à 15 minutes vers le responsable (`admin`) si non traitée
 * - Clôture automatique ("Résolue automatiquement") dès que l'événement attendu survient
 * - Respect des préférences par utilisateur et des heures calmes (contournées par les alertes `critical`)
 * - File d'attente `notification_queue` avec re-tentatives (backoff exponentiel jusqu'à 5 essais).
 */
final class AlertService
{
    public const TYPES = [
        'vital_critical' => 'Constante vitale critique',
        'vital_out_of_range' => 'Constante hors cible',
        'intake_late' => 'Prise de médicament en retard (> 30 min)',
        'measure_late' => 'Mesure attendue en retard (> 30 min)',
        'attendance_missing' => 'Pointage d’arrivée absent (> 15 min)',
        'shift_uncovered' => 'Créneau non couvert (< 48 h)',
        'attendance_pending' => 'Pointage ou oubli à valider',
    ];

    /**
     * Crée une alerte ouverte si aucune alerte ouverte (`new` ou `seen`) ne porte déjà la même `dedupe_key`.
     * Enfile les notifications (Web Push et E-mail) selon les destinataires, leurs préférences et les heures calmes.
     *
     * @param array{
     *   patient_id?: int,
     *   type: string,
     *   level: string,
     *   source_type?: ?string,
     *   source_id?: ?int,
     *   dedupe_key: string,
     *   title: string,
     *   message: string,
     *   target_url?: ?string,
     *   recipient_user_ids?: array<int, int>,
     *   created_by?: ?int,
     *   now_utc?: ?string
     * } $data
     * @return array{alert: array<string, mixed>, created: bool, queued_notifications: int}
     */
    public static function trigger(array $data): array
    {
        $pdo = Database::connection();
        $patientId = (int) ($data['patient_id'] ?? 1);
        $dedupeKey = trim((string) $data['dedupe_key']);
        $nowUtc = (string) ($data['now_utc'] ?? gmdate('Y-m-d H:i:s'));

        if ($dedupeKey === '') {
            throw new InvalidArgumentException('dedupe_key obligatoire pour déclencher une alerte.');
        }

        // 1. Check existing open alert with same dedupe_key
        $chk = $pdo->prepare(
            "SELECT * FROM alerts
             WHERE patient_id = :pid
               AND dedupe_key = :dk
               AND status IN ('new', 'seen')
               AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1"
        );
        $chk->execute([
            'pid' => $patientId,
            'dk' => $dedupeKey,
        ]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
            return [
                'alert' => $existing,
                'created' => false,
                'queued_notifications' => 0,
            ];
        }

        $type = (string) ($data['type'] ?? 'vital_out_of_range');
        $level = in_array($data['level'] ?? 'warning', ['info', 'warning', 'critical'], true)
            ? (string) $data['level']
            : 'warning';
        $sourceType = isset($data['source_type']) ? (string) $data['source_type'] : null;
        $sourceId = isset($data['source_id']) && (int) $data['source_id'] > 0 ? (int) $data['source_id'] : null;
        $title = trim((string) ($data['title'] ?? 'Alerte Prime Care'));
        $message = trim((string) ($data['message'] ?? ''));
        $targetUrl = isset($data['target_url']) ? (string) $data['target_url'] : '/alerts';
        $createdBy = isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null;

        $ins = $pdo->prepare(
            "INSERT INTO alerts
             (patient_id, type, level, source_type, source_id, dedupe_key, open_dedupe_key,
              title, message, target_url, status, created_at, updated_at, created_by)
             VALUES
             (:pid, :type, :level, :st, :sid, :dk, :odk,
              :title, :msg, :url, 'new', :c_at, :u_at, :by)"
        );
        $ins->execute([
            'pid' => $patientId,
            'type' => $type,
            'level' => $level,
            'st' => $sourceType,
            'sid' => $sourceId,
            'dk' => $dedupeKey,
            'odk' => $dedupeKey,
            'title' => substr($title, 0, 180),
            'msg' => $message,
            'url' => $targetUrl,
            'c_at' => $nowUtc,
            'u_at' => $nowUtc,
            'by' => $createdBy,
        ]);

        $alertId = (int) $pdo->lastInsertId();
        $alert = self::findById($alertId) ?? [
            'id' => $alertId,
            'patient_id' => $patientId,
            'type' => $type,
            'level' => $level,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'dedupe_key' => $dedupeKey,
            'title' => $title,
            'message' => $message,
            'target_url' => $targetUrl,
            'status' => 'new',
            'created_at' => $nowUtc,
        ];

        $recipientIds = $data['recipient_user_ids'] ?? self::defaultRecipientUserIds($type, $patientId, $nowUtc);
        $queuedCount = self::enqueueNotificationsForUsers($alert, $recipientIds, $nowUtc);

        return [
            'alert' => $alert,
            'created' => true,
            'queued_notifications' => $queuedCount,
        ];
    }

    /**
     * Clôture automatiquement toutes les alertes ouvertes correspondant à une clé ou un préfixe/source.
     */
    public static function autoResolveByDedupeKey(
        string $dedupeKey,
        string $resolution = 'Résolue automatiquement',
        int $patientId = 1,
        ?string $nowUtc = null
    ): int {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = 'handled',
                 open_dedupe_key = NULL,
                 handled_at = :hat,
                 resolution = :res,
                 updated_at = :u_at
             WHERE patient_id = :pid
               AND dedupe_key = :dk
               AND status IN ('new', 'seen')
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'hat' => $nowUtc,
            'res' => $resolution,
            'u_at' => $nowUtc,
            'pid' => $patientId,
            'dk' => $dedupeKey,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Clôture automatiquement les alertes ouvertes liées à une source (`source_type`, `source_id`).
     */
    public static function autoResolveBySource(
        string $sourceType,
        int $sourceId,
        string $resolution = 'Résolue automatiquement',
        int $patientId = 1,
        ?string $nowUtc = null
    ): int {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = 'handled',
                 open_dedupe_key = NULL,
                 handled_at = :hat,
                 resolution = :res,
                 updated_at = :u_at
             WHERE patient_id = :pid
               AND source_type = :st
               AND source_id = :sid
               AND status IN ('new', 'seen')
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'hat' => $nowUtc,
            'res' => $resolution,
            'u_at' => $nowUtc,
            'pid' => $patientId,
            'st' => $sourceType,
            'sid' => $sourceId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Appelé immédiatement après l'enregistrement d'une mesure médicale (`measures`).
     * - Si la mesure est `critical` (crit_low / crit_high) -> déclenche `vital_critical` (critical)
     * - Si la mesure est `warn` (low / high) -> déclenche `vital_out_of_range` (warning)
     * - Si la mesure est `ok` (normale) -> clôture automatiquement toute alerte `vital_critical` ou `vital_out_of_range` ouverte sur ce type de mesure !
     * - Clôture automatiquement toute alerte `measure_late` sur une attente (`measure_expectations`) qui vient d'être remplie.
     *
     * @param array<string, mixed> $measure
     * @param array<string, mixed> $measureType
     */
    public static function onMeasureRecorded(array $measure, array $measureType, ?int $expectationId = null): void
    {
        $patientId = (int) ($measure['patient_id'] ?? 1);
        $measureId = (int) ($measure['id'] ?? 0);
        $typeId = (int) ($measure['measure_type_id'] ?? $measureType['id'] ?? 0);
        $typeCode = (string) ($measureType['code'] ?? 'glycemia');
        $typeLabel = (string) ($measureType['label'] ?? 'Mesure');
        $unit = (string) ($measure['unit_recorded'] ?? $measureType['unit'] ?? '');
        $valStr = (string) ($measure['value'] ?? '');
        if (isset($measure['value2']) && $measure['value2'] !== null && $measure['value2'] !== '') {
            $valStr .= '/' . $measure['value2'];
        }
        $alertLevel = (string) ($measure['alert_level'] ?? 'ok');
        $recordedBy = isset($measure['recorded_by']) ? (int) $measure['recorded_by'] : null;

        if ($expectationId !== null && $expectationId > 0) {
            self::autoResolveByDedupeKey(
                "measure_late:{$expectationId}",
                'Résolue automatiquement (mesure saisie)',
                $patientId
            );
        }

        // Also resolve any open measure_late for this measure_type_id whose expectation is now done
        $pdo = Database::connection();
        $doneExpStmt = $pdo->prepare(
            "SELECT id FROM measure_expectations
             WHERE patient_id = :pid AND measure_type_id = :mtid AND status = 'done'"
        );
        $doneExpStmt->execute(['pid' => $patientId, 'mtid' => $typeId]);
        foreach ($doneExpStmt->fetchAll(PDO::FETCH_COLUMN) as $expId) {
            self::autoResolveByDedupeKey(
                'measure_late:' . (int) $expId,
                'Résolue automatiquement (mesure saisie)',
                $patientId
            );
        }

        if ($alertLevel === 'ok') {
            // Un contrôle normal clôture automatiquement les alertes vitales ouvertes sur ce type de constante
            $stmt = $pdo->prepare(
                "UPDATE alerts
                 SET status = 'handled',
                     open_dedupe_key = NULL,
                     handled_at = :hat,
                     resolution = 'Résolue automatiquement (contrôle normal enregistré)',
                     updated_at = :u_at
                 WHERE patient_id = :pid
                   AND type IN ('vital_critical', 'vital_out_of_range')
                   AND dedupe_key LIKE :prefix
                   AND status IN ('new', 'seen')
                   AND deleted_at IS NULL"
            );
            $now = gmdate('Y-m-d H:i:s');
            $stmt->execute([
                'hat' => $now,
                'u_at' => $now,
                'pid' => $patientId,
                'prefix' => "vital_%:{$typeId}:%",
            ]);
            return;
        }

        $adminsAndCoords = self::getUserIdsByRoles(['admin', 'coordinator'], $patientId);

        if ($alertLevel === 'critical') {
            self::trigger([
                'patient_id' => $patientId,
                'type' => 'vital_critical',
                'level' => 'critical',
                'source_type' => 'measure',
                'source_id' => $measureId,
                'dedupe_key' => "vital_critical:{$typeId}:{$measureId}",
                'title' => "{$typeLabel} critique : {$valStr} {$unit}",
                'message' => "Valeur critique enregistrée pour M. Henri Benali ({$typeLabel} : {$valStr} {$unit}). Intervention et contrôle immédiats requis.",
                'target_url' => '/health/' . $typeCode,
                'recipient_user_ids' => $adminsAndCoords,
                'created_by' => $recordedBy,
            ]);
        } elseif ($alertLevel === 'warn') {
            self::trigger([
                'patient_id' => $patientId,
                'type' => 'vital_out_of_range',
                'level' => 'warning',
                'source_type' => 'measure',
                'source_id' => $measureId,
                'dedupe_key' => "vital_warn:{$typeId}:{$measureId}",
                'title' => "{$typeLabel} hors cible : {$valStr} {$unit}",
                'message' => "Valeur hors cible enregistrée ({$typeLabel} : {$valStr} {$unit}). Surveillance recommandée.",
                'target_url' => '/health/' . $typeCode,
                'recipient_user_ids' => $adminsAndCoords,
                'created_by' => $recordedBy,
            ]);
        }
    }

    /**
     * Appelé lorsqu'une prise de médicament est marquée "Donné" ou "Non donné".
     * Clôture automatiquement toute alerte `intake_late` ouverte pour cette prise.
     */
    public static function onIntakeHandled(int $intakeId, int $patientId = 1): void
    {
        self::autoResolveByDedupeKey(
            "intake_late:{$intakeId}",
            'Résolue automatiquement (prise validée)',
            $patientId
        );
    }

    /**
     * Appelé lorsqu'un pointage est enregistré (`in` / `out` / `declared`).
     * - Si pointage d'arrivée (`in`) sur un créneau -> clôture automatiquement `attendance_missing:{shift_id}`
     * - Si pointage en statut `pending` (oubli déclaré ou hors zone / position refusée) -> déclenche `attendance_pending` (`info`).
     *
     * @param array<string, mixed> $attendance
     */
    public static function onAttendanceRecorded(array $attendance): void
    {
        $patientId = (int) ($attendance['patient_id'] ?? 1);
        $attId = (int) ($attendance['id'] ?? 0);
        $shiftId = isset($attendance['shift_id']) && $attendance['shift_id'] !== null ? (int) $attendance['shift_id'] : null;
        $type = (string) ($attendance['type'] ?? 'in');
        $status = (string) ($attendance['status'] ?? 'valid');
        $userId = (int) ($attendance['user_id'] ?? 0);

        if ($type === 'in' && $shiftId !== null && $status !== 'rejected') {
            self::autoResolveByDedupeKey(
                "attendance_missing:{$shiftId}",
                'Résolue automatiquement (pointage d’arrivée enregistré)',
                $patientId
            );
        }

        if ($status === 'pending' && $attId > 0) {
            $userName = trim(((string) ($attendance['first_name'] ?? '')) . ' ' . ((string) ($attendance['last_name'] ?? '')));
            if ($userName === '' && $userId > 0) {
                $pdo = Database::connection();
                $uStmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = :id');
                $uStmt->execute(['id' => $userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow) {
                    $userName = trim($uRow['first_name'] . ' ' . $uRow['last_name']);
                }
            }
            $reason = (string) ($attendance['reason'] ?? 'Vérification requise');
            $typeLabel = ($type === 'out') ? 'départ' : 'arrivée';

            self::trigger([
                'patient_id' => $patientId,
                'type' => 'attendance_pending',
                'level' => 'info',
                'source_type' => 'attendance',
                'source_id' => $attId,
                'dedupe_key' => "attendance_pending:{$attId}",
                'title' => "Pointage de {$typeLabel} à valider ({$userName})",
                'message' => "Pointage de {$typeLabel} de {$userName} en attente de validation ({$reason}).",
                'target_url' => '/attendance',
                'recipient_user_ids' => self::getUserIdsByRoles(['admin', 'coordinator'], $patientId),
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }
    }

    /**
     * Appelé lorsqu'un pointage en attente est validé ou refusé.
     */
    public static function onAttendanceValidatedOrRejected(int $attendanceId, int $actorId, string $resolution = 'Résolue automatiquement'): void
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = 'handled',
                 open_dedupe_key = NULL,
                 handled_by = :by,
                 handled_at = :hat,
                 resolution = :res,
                 updated_at = :u_at
             WHERE dedupe_key = :dk
               AND status IN ('new', 'seen')
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'by' => $actorId,
            'hat' => $now,
            'res' => $resolution,
            'u_at' => $now,
            'dk' => "attendance_pending:{$attendanceId}",
        ]);
    }

    /**
     * Appelé lorsqu'un créneau du planning est créé ou modifié.
     * - Si le créneau est désormais attribué (`user_id IS NOT NULL`), clôture automatiquement `shift_uncovered:{shift_id}`.
     * - Si le créneau est non couvert (`user_id IS NULL`) et débute dans moins de 48 h, déclenche `shift_uncovered`.
     */
    public static function onShiftSaved(int $shiftId): void
    {
        $shift = PlanningService::findById($shiftId);
        if ($shift === null) {
            return;
        }
        $patientId = (int) $shift['patient_id'];

        if ($shift['user_id'] !== null || (string) $shift['status'] === 'cancelled') {
            self::autoResolveByDedupeKey(
                "shift_uncovered:{$shiftId}",
                'Résolue automatiquement (créneau attribué)',
                $patientId
            );
            return;
        }

        $nowTs = time();
        $startTs = (new DateTimeImmutable((string) $shift['start_at'], new DateTimeZone('UTC')))->getTimestamp();
        if ($startTs > $nowTs && $startTs <= $nowTs + 48 * 3600) {
            $localTz = PlanningService::localTz();
            $stLocal = (new DateTimeImmutable((string) $shift['start_at'], new DateTimeZone('UTC')))->setTimezone($localTz);
            $etLocal = (new DateTimeImmutable((string) $shift['end_at'], new DateTimeZone('UTC')))->setTimezone($localTz);

            self::trigger([
                'patient_id' => $patientId,
                'type' => 'shift_uncovered',
                'level' => 'warning',
                'source_type' => 'shift',
                'source_id' => $shiftId,
                'dedupe_key' => "shift_uncovered:{$shiftId}",
                'title' => 'Créneau non couvert le ' . $stLocal->format('d/m à H\hi'),
                'message' => sprintf(
                    'Aucune aide-soignante n’est affectée au créneau du %s (%s – %s) à moins de 48 heures.',
                    $stLocal->format('d/m/Y'),
                    $stLocal->format('H\hi'),
                    $etLocal->format('H\hi')
                ),
                'target_url' => '/planning?week=' . $stLocal->format('Y-m-d'),
                'recipient_user_ids' => self::getUserIdsByRoles(['admin', 'coordinator'], $patientId),
            ]);
        }
    }

    /**
     * Tâche périodique `alerts:scan` (exécutée par `cron/run.php` toutes les 5 minutes) :
     * 1. Détecte les prises en retard > 30 min (`intake_late`)
     * 2. Détecte les mesures attendues en retard > 30 min (`measure_late`)
     * 3. Détecte les pointages d'arrivée absents > 15 min après le début d'un créneau (`attendance_missing`)
     * 4. Détecte les créneaux non couverts à moins de 48 h (`shift_uncovered`)
     * 5. Escalade au responsable (`admin`) toute alerte ouverte non traitée depuis >= 15 minutes (`escalated_at`).
     *
     * @return array{triggered: int, escalated: int, auto_resolved: int}
     */
    public static function scanPeriodicAlerts(int $patientId = 1, ?string $nowUtc = null): array
    {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $localTz = PlanningService::localTz();
        $nowDt = $nowUtc !== null
            ? new DateTimeImmutable($nowUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);
        $nowStr = $nowDt->format('Y-m-d H:i:s');

        $triggered = 0;
        $escalated = 0;
        $autoResolved = 0;

        $onDutyIds = self::getOnDutyUserIds($patientId, $nowStr);
        $adminIds = self::getUserIdsByRoles(['admin'], $patientId);
        $adminAndCoordIds = self::getUserIdsByRoles(['admin', 'coordinator'], $patientId);

        // -----------------------------------------------------------------
        // 1. Prises de médicaments en retard > 30 min (dans les dernières 12h)
        // -----------------------------------------------------------------
        $lateIntakeCutoff = $nowDt->modify('-30 minutes')->format('Y-m-d H:i:s');
        $lateIntakeMin = $nowDt->modify('-12 hours')->format('Y-m-d H:i:s');

        $intakeStmt = $pdo->prepare(
            "SELECT mi.id, mi.scheduled_at, m.name AS med_name, p.dose, p.unit
             FROM medication_intakes mi
             INNER JOIN prescriptions p ON p.id = mi.prescription_id
             INNER JOIN medications m ON m.id = p.medication_id
             WHERE mi.patient_id = :pid
               AND mi.deleted_at IS NULL
               AND mi.status = 'pending'
               AND mi.scheduled_at >= :min_at
               AND mi.scheduled_at <= :cutoff_at"
        );
        $intakeStmt->execute([
            'pid' => $patientId,
            'min_at' => $lateIntakeMin,
            'cutoff_at' => $lateIntakeCutoff,
        ]);

        foreach ($intakeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $iid = (int) $row['id'];
            $schedLocal = (new DateTimeImmutable((string) $row['scheduled_at'], $utcTz))->setTimezone($localTz);
            $recipients = array_values(array_unique(array_merge($onDutyIds, $adminIds)));

            $res = self::trigger([
                'patient_id' => $patientId,
                'type' => 'intake_late',
                'level' => 'warning',
                'source_type' => 'medication_intake',
                'source_id' => $iid,
                'dedupe_key' => "intake_late:{$iid}",
                'title' => "Prise en retard : {$row['med_name']} ({$schedLocal->format('H\hi')})",
                'message' => "La prise de {$row['med_name']} ({$row['dose']} {$row['unit']}) prévue à {$schedLocal->format('H\hi')} a plus de 30 minutes de retard.",
                'target_url' => '/treatments',
                'recipient_user_ids' => $recipients,
                'now_utc' => $nowStr,
            ]);
            if ($res['created']) {
                $triggered++;
            }
        }

        // -----------------------------------------------------------------
        // 2. Mesures attendues en retard > 30 min (dans les dernières 12h)
        // -----------------------------------------------------------------
        $expStmt = $pdo->prepare(
            "SELECT me.id, me.scheduled_at, mt.code AS type_code, mt.label AS type_label
             FROM measure_expectations me
             INNER JOIN measure_types mt ON mt.id = me.measure_type_id
             WHERE me.patient_id = :pid
               AND me.deleted_at IS NULL
               AND me.status != 'done'
               AND me.scheduled_at >= :min_at
               AND me.scheduled_at <= :cutoff_at"
        );
        $expStmt->execute([
            'pid' => $patientId,
            'min_at' => $lateIntakeMin,
            'cutoff_at' => $lateIntakeCutoff,
        ]);

        foreach ($expStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eid = (int) $row['id'];
            $schedLocal = (new DateTimeImmutable((string) $row['scheduled_at'], $utcTz))->setTimezone($localTz);
            $recipients = $onDutyIds !== [] ? $onDutyIds : $adminAndCoordIds;

            $res = self::trigger([
                'patient_id' => $patientId,
                'type' => 'measure_late',
                'level' => 'warning',
                'source_type' => 'measure_expectation',
                'source_id' => $eid,
                'dedupe_key' => "measure_late:{$eid}",
                'title' => "Mesure attendue en retard : {$row['type_label']} ({$schedLocal->format('H\hi')})",
                'message' => "La mesure de {$row['type_label']} prévue à {$schedLocal->format('H\hi')} a plus de 30 minutes de retard.",
                'target_url' => '/health/' . $row['type_code'],
                'recipient_user_ids' => $recipients,
                'now_utc' => $nowStr,
            ]);
            if ($res['created']) {
                $triggered++;
            }
        }

        // -----------------------------------------------------------------
        // 3. Pointage d'arrivée absent > 15 min après le début du créneau
        // -----------------------------------------------------------------
        $missingPunchCutoff = $nowDt->modify('-15 minutes')->format('Y-m-d H:i:s');
        $missingPunchMin = $nowDt->modify('-12 hours')->format('Y-m-d H:i:s');

        $shiftStmt = $pdo->prepare(
            "SELECT s.id, s.user_id, s.start_at, s.end_at, u.first_name, u.last_name
             FROM shifts s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.patient_id = :pid
               AND s.deleted_at IS NULL
               AND s.status != 'cancelled'
               AND s.user_id IS NOT NULL
               AND s.start_at >= :min_at
               AND s.start_at <= :cutoff_at
               AND s.end_at >= :now_at
               AND NOT EXISTS (
                   SELECT 1 FROM attendances a
                   WHERE a.shift_id = s.id
                     AND a.type = 'in'
                     AND a.status != 'rejected'
                     AND a.deleted_at IS NULL
               )"
        );
        $shiftStmt->execute([
            'pid' => $patientId,
            'min_at' => $missingPunchMin,
            'cutoff_at' => $missingPunchCutoff,
            'now_at' => $nowStr,
        ]);

        foreach ($shiftStmt->fetchAll(PDO::FETCH_ASSOC) as $sh) {
            $sid = (int) $sh['id'];
            $uid = (int) $sh['user_id'];
            $cgName = trim($sh['first_name'] . ' ' . $sh['last_name']);
            $stLocal = (new DateTimeImmutable((string) $sh['start_at'], $utcTz))->setTimezone($localTz);
            $recipients = array_values(array_unique(array_merge($adminAndCoordIds, [$uid])));

            $res = self::trigger([
                'patient_id' => $patientId,
                'type' => 'attendance_missing',
                'level' => 'critical',
                'source_type' => 'shift',
                'source_id' => $sid,
                'dedupe_key' => "attendance_missing:{$sid}",
                'title' => "Pointage d’arrivée absent : {$cgName} ({$stLocal->format('H\hi')})",
                'message' => "{$cgName} n’a pas pointé son arrivée pour la garde débutant à {$stLocal->format('H\hi')} (+15 min).",
                'target_url' => '/attendance',
                'recipient_user_ids' => $recipients,
                'now_utc' => $nowStr,
            ]);
            if ($res['created']) {
                $triggered++;
            }
        }

        // -----------------------------------------------------------------
        // 4. Créneau non couvert à moins de 48 h
        // -----------------------------------------------------------------
        $horizon48h = $nowDt->modify('+48 hours')->format('Y-m-d H:i:s');
        $uncovStmt = $pdo->prepare(
            "SELECT id, start_at, end_at
             FROM shifts
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status != 'cancelled'
               AND user_id IS NULL
               AND start_at > :now_at
               AND start_at <= :h48"
        );
        $uncovStmt->execute([
            'pid' => $patientId,
            'now_at' => $nowStr,
            'h48' => $horizon48h,
        ]);

        foreach ($uncovStmt->fetchAll(PDO::FETCH_ASSOC) as $ush) {
            $sid = (int) $ush['id'];
            $stLocal = (new DateTimeImmutable((string) $ush['start_at'], $utcTz))->setTimezone($localTz);
            $etLocal = (new DateTimeImmutable((string) $ush['end_at'], $utcTz))->setTimezone($localTz);

            $res = self::trigger([
                'patient_id' => $patientId,
                'type' => 'shift_uncovered',
                'level' => 'warning',
                'source_type' => 'shift',
                'source_id' => $sid,
                'dedupe_key' => "shift_uncovered:{$sid}",
                'title' => 'Créneau non couvert le ' . $stLocal->format('d/m à H\hi'),
                'message' => sprintf(
                    'Aucune aide-soignante n’est affectée au créneau du %s (%s – %s) à moins de 48 heures.',
                    $stLocal->format('d/m/Y'),
                    $stLocal->format('H\hi'),
                    $etLocal->format('H\hi')
                ),
                'target_url' => '/planning?week=' . $stLocal->format('Y-m-d'),
                'recipient_user_ids' => $adminAndCoordIds,
                'now_utc' => $nowStr,
            ]);
            if ($res['created']) {
                $triggered++;
            }
        }

        // -----------------------------------------------------------------
        // 5. Escalade au responsable (`admin`) si non traitée après 15 min
        // -----------------------------------------------------------------
        $escCutoff = $nowDt->modify('-15 minutes')->format('Y-m-d H:i:s');
        $escStmt = $pdo->prepare(
            "SELECT * FROM alerts
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status IN ('new', 'seen')
               AND escalated_at IS NULL
               AND created_at <= :esc_cutoff"
        );
        $escStmt->execute([
            'pid' => $patientId,
            'esc_cutoff' => $escCutoff,
        ]);

        foreach ($escStmt->fetchAll(PDO::FETCH_ASSOC) as $openAlert) {
            $aid = (int) $openAlert['id'];
            $upd = $pdo->prepare('UPDATE alerts SET escalated_at = :esc, updated_at = :u_at WHERE id = :id');
            $upd->execute([
                'esc' => $nowStr,
                'u_at' => $nowStr,
                'id' => $aid,
            ]);

            $escAlert = $openAlert;
            $escAlert['title'] = '[ESCALADE 15 min] ' . $openAlert['title'];
            self::enqueueNotificationsForUsers($escAlert, $adminIds, $nowStr);
            $escalated++;
        }

        return [
            'triggered' => $triggered,
            'escalated' => $escalated,
            'auto_resolved' => $autoResolved,
        ];
    }

    /**
     * Retourne les identifiants des aides-soignantes actuellement en poste (via pointage actif ou planning en cours).
     *
     * @return array<int, int>
     */
    public static function getOnDutyUserIds(int $patientId = 1, ?string $nowUtc = null): array
    {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');
        $nowTs = (new DateTimeImmutable($nowUtc, new DateTimeZone('UTC')))->getTimestamp();
        $cutoff = gmdate('Y-m-d H:i:s', $nowTs - 16 * 3600);

        // 1. Check active clocked-in users
        $attStmt = $pdo->prepare(
            "SELECT a.user_id, a.type
             FROM attendances a
             WHERE a.patient_id = :pid
               AND a.deleted_at IS NULL
               AND a.status != 'rejected'
               AND COALESCE(a.declared_at, a.recorded_at) >= :cutoff
               AND COALESCE(a.declared_at, a.recorded_at) <= :now_utc
             ORDER BY COALESCE(a.declared_at, a.recorded_at) DESC, a.id DESC"
        );
        $attStmt->execute([
            'pid' => $patientId,
            'cutoff' => $cutoff,
            'now_utc' => $nowUtc,
        ]);

        $seenUsers = [];
        $clockedInIds = [];
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int) $r['user_id'];
            if (isset($seenUsers[$uid])) {
                continue;
            }
            $seenUsers[$uid] = true;
            if ((string) $r['type'] === 'in') {
                $clockedInIds[] = $uid;
            }
        }

        if ($clockedInIds !== []) {
            return $clockedInIds;
        }

        // 2. Fallback to planned shifts active right now
        $shStmt = $pdo->prepare(
            "SELECT DISTINCT user_id FROM shifts
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status != 'cancelled'
               AND user_id IS NOT NULL
               AND start_at <= :now_start
               AND end_at >= :now_end"
        );
        $shStmt->execute([
            'pid' => $patientId,
            'now_start' => $nowUtc,
            'now_end' => $nowUtc,
        ]);

        return array_map('intval', $shStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Retourne les préférences de notification d'un utilisateur (crée les valeurs par défaut si absentes).
     *
     * @return array<string, mixed>
     */
    public static function getUserNotificationSettings(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notification_settings WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $defaultPrefs = [];
        foreach (array_keys(self::TYPES) as $typeKey) {
            $defaultPrefs[$typeKey] = ['push' => true, 'email' => true];
        }

        if ($row === false) {
            $now = gmdate('Y-m-d H:i:s');
            $ins = $pdo->prepare(
                "INSERT INTO notification_settings
                 (user_id, push_enabled, email_enabled, sms_enabled, quiet_hours_enabled, quiet_start, quiet_end, preferences_json, created_at, updated_at, created_by)
                 VALUES (:uid, 1, 1, 0, 1, '22:00', '07:00', :prefs, :c_at, :u_at, :by)"
            );
            $ins->execute([
                'uid' => $userId,
                'prefs' => json_encode($defaultPrefs),
                'c_at' => $now,
                'u_at' => $now,
                'by' => $userId,
            ]);
            return [
                'user_id' => $userId,
                'push_enabled' => 1,
                'email_enabled' => 1,
                'sms_enabled' => 0,
                'quiet_hours_enabled' => 1,
                'quiet_start' => '22:00',
                'quiet_end' => '07:00',
                'preferences' => $defaultPrefs,
            ];
        }

        $decoded = json_decode((string) ($row['preferences_json'] ?? '{}'), true);
        $prefs = is_array($decoded) ? array_replace_recursive($defaultPrefs, $decoded) : $defaultPrefs;
        $row['preferences'] = $prefs;
        return $row;
    }

    /**
     * Met à jour les préférences de notification d'un utilisateur.
     *
     * @param array<string, mixed> $input
     */
    public static function updateUserNotificationSettings(int $userId, array $input): void
    {
        $current = self::getUserNotificationSettings($userId);
        $pushEnabled = !empty($input['push_enabled']) ? 1 : 0;
        $emailEnabled = !empty($input['email_enabled']) ? 1 : 0;
        $quietEnabled = !empty($input['quiet_hours_enabled']) ? 1 : 0;

        $quietStart = trim((string) ($input['quiet_start'] ?? $current['quiet_start']));
        $quietEnd = trim((string) ($input['quiet_end'] ?? $current['quiet_end']));
        if (!preg_match('/^\d{2}:\d{2}$/', $quietStart)) {
            $quietStart = '22:00';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $quietEnd)) {
            $quietEnd = '07:00';
        }

        $prefs = $current['preferences'];
        if (isset($input['types']) && is_array($input['types'])) {
            foreach (array_keys(self::TYPES) as $typeKey) {
                $prefs[$typeKey] = [
                    'push' => !empty($input['types'][$typeKey]['push']),
                    'email' => !empty($input['types'][$typeKey]['email']),
                ];
            }
        }

        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE notification_settings
             SET push_enabled = :push, email_enabled = :email, quiet_hours_enabled = :qe,
                 quiet_start = :qs, quiet_end = :qend, preferences_json = :prefs, updated_at = :u_at
             WHERE user_id = :uid'
        );
        $stmt->execute([
            'push' => $pushEnabled,
            'email' => $emailEnabled,
            'qe' => $quietEnabled,
            'qs' => $quietStart,
            'qend' => $quietEnd,
            'prefs' => json_encode($prefs),
            'u_at' => $now,
            'uid' => $userId,
        ]);

        AuditService::log('profile.notification_settings', 'notification_settings', $userId, null, [
            'push_enabled' => $pushEnabled,
            'email_enabled' => $emailEnabled,
            'quiet_hours_enabled' => $quietEnabled,
            'quiet_start' => $quietStart,
            'quiet_end' => $quietEnd,
        ], $userId);
    }

    /**
     * Vérifie si une heure donnée (UTC ou locale) tombe dans la plage d'heures calmes de l'utilisateur.
     */
    public static function isInQuietHours(string $quietStart, string $quietEnd, ?string $nowUtc = null): bool
    {
        $localTz = PlanningService::localTz();
        $utcTz = new DateTimeZone('UTC');
        $dtUtc = $nowUtc !== null
            ? new DateTimeImmutable($nowUtc, $utcTz)
            : new DateTimeImmutable('now', $utcTz);
        $localHm = $dtUtc->setTimezone($localTz)->format('H:i');

        if ($quietStart === $quietEnd) {
            return false;
        }
        if ($quietStart < $quietEnd) {
            // Same-day window, e.g. 13:00 -> 15:00
            return ($localHm >= $quietStart && $localHm < $quietEnd);
        }
        // Overnight window, e.g. 22:00 -> 07:00
        return ($localHm >= $quietStart || $localHm < $quietEnd);
    }

    /**
     * Enfile les notifications (Web Push et E-mail) dans `notification_queue` pour les destinataires d'une alerte.
     * Les alertes `critical` contournent toujours les heures calmes.
     * Les alertes non critiques (`warning`, `info`) ne génèrent pas d'envoi pendant les heures calmes.
     *
     * @param array<string, mixed> $alert
     * @param array<int, int> $userIds
     */
    public static function enqueueNotificationsForUsers(array $alert, array $userIds, ?string $nowUtc = null): int
    {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');
        $alertId = isset($alert['id']) ? (int) $alert['id'] : null;
        $type = (string) ($alert['type'] ?? 'vital_out_of_range');
        $level = (string) ($alert['level'] ?? 'warning');
        $title = (string) ($alert['title'] ?? 'Alerte Prime Care');
        $message = (string) ($alert['message'] ?? '');
        $targetUrl = (string) ($alert['target_url'] ?? '/alerts');
        $dedupeKey = (string) ($alert['dedupe_key'] ?? ('alert-' . ($alertId ?? time())));

        $queued = 0;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        foreach ($userIds as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $uStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, is_active FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $uStmt->execute(['id' => $uid]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int) $user['is_active'] !== 1) {
                continue;
            }

            $settings = self::getUserNotificationSettings($uid);
            $inQuietHours = ((int) $settings['quiet_hours_enabled'] === 1)
                && self::isInQuietHours((string) $settings['quiet_start'], (string) $settings['quiet_end'], $nowUtc);

            // Critical alerts always bypass quiet hours; non-critical alerts do not send during quiet hours
            if ($inQuietHours && $level !== 'critical') {
                continue;
            }

            $typePrefs = $settings['preferences'][$type] ?? ['push' => true, 'email' => true];
            $allowPush = ((int) $settings['push_enabled'] === 1) && !empty($typePrefs['push']);
            $allowEmail = ((int) $settings['email_enabled'] === 1) && !empty($typePrefs['email']);

            $insQueue = $pdo->prepare(
                "INSERT INTO notification_queue
                 (alert_id, user_id, channel, recipient, subject, content, status, attempts, max_attempts, next_try_at, created_at, updated_at)
                 VALUES
                 (:aid, :uid, :chan, :recip, :subj, :content, 'pending', 0, 5, :next_at, :c_at, :u_at)"
            );

            // 1. E-mail notification
            if ($allowEmail && !empty($user['email'])) {
                $insQueue->execute([
                    'aid' => $alertId,
                    'uid' => $uid,
                    'chan' => 'email',
                    'recip' => (string) $user['email'],
                    'subj' => $title,
                    'content' => json_encode([
                        'recipient_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                        'alert' => [
                            'id' => $alertId,
                            'type' => $type,
                            'level' => $level,
                            'title' => $title,
                            'message' => $message,
                            'target_url' => $targetUrl,
                            'dedupe_key' => $dedupeKey,
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'next_at' => $nowUtc,
                    'c_at' => $nowUtc,
                    'u_at' => $nowUtc,
                ]);
                $queued++;
            }

            // 2. Web Push notifications (for each active subscription of this user)
            if ($allowPush) {
                $subStmt = $pdo->prepare(
                    'SELECT id, endpoint FROM push_subscriptions WHERE user_id = :uid AND deleted_at IS NULL'
                );
                $subStmt->execute(['uid' => $uid]);
                foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
                    $insQueue->execute([
                        'aid' => $alertId,
                        'uid' => $uid,
                        'chan' => 'push',
                        'recip' => (string) $sub['endpoint'],
                        'subj' => $title,
                        'content' => json_encode([
                            'subscription_id' => (int) $sub['id'],
                            'urgency' => $level === 'critical' ? 'high' : 'normal',
                            'payload' => [
                                'title' => $title,
                                'body' => $message,
                                'url' => $targetUrl,
                                'tag' => $dedupeKey,
                                'level' => $level,
                                'icon' => '/assets/brand/icon-192.png',
                                'badge' => '/assets/brand/badge-96.png',
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                        'next_at' => $nowUtc,
                        'c_at' => $nowUtc,
                        'u_at' => $nowUtc,
                    ]);
                    $queued++;
                }
            }
        }

        return $queued;
    }

    /**
     * Dépile et envoie les notifications en attente dans `notification_queue` (`notifications:send`).
     * Re-tente jusqu'à 5 fois avec backoff exponentiel en cas d'échec temporaire.
     *
     * @return array{sent: int, retried: int, failed: int}
     */
    public static function processNotificationQueue(int $limit = 50, ?string $nowUtc = null): array
    {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');
        $nowTs = (new DateTimeImmutable($nowUtc, new DateTimeZone('UTC')))->getTimestamp();

        $stmt = $pdo->prepare(
            "SELECT * FROM notification_queue
             WHERE status = 'pending'
               AND deleted_at IS NULL
               AND next_try_at <= :now_utc
             ORDER BY next_try_at ASC, id ASC
             LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute(['now_utc' => $nowUtc]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $retried = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $qid = (int) $row['id'];
            $channel = (string) $row['channel'];
            $attempts = (int) $row['attempts'] + 1;
            $maxAttempts = (int) ($row['max_attempts'] ?? 5);
            $content = json_decode((string) $row['content'], true) ?: [];

            $ok = false;
            $permanentFailure = false;
            $errorMsg = null;

            if ($channel === 'email') {
                $alertData = is_array($content['alert'] ?? null) ? $content['alert'] : [
                    'level' => 'info',
                    'title' => (string) $row['subject'],
                    'message' => (string) ($content['message'] ?? ''),
                    'target_url' => '/',
                ];
                $recipientName = (string) ($content['recipient_name'] ?? '');
                $ok = MailService::sendAlertEmail((string) $row['recipient'], $recipientName, $alertData);
                if (!$ok) {
                    $errorMsg = 'Échec d’envoi SMTP PHPMailer.';
                }
            } elseif ($channel === 'push') {
                $subId = (int) ($content['subscription_id'] ?? 0);
                $subStmt = $pdo->prepare('SELECT * FROM push_subscriptions WHERE id = :id AND deleted_at IS NULL LIMIT 1');
                $subStmt->execute(['id' => $subId]);
                $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);

                if (!$subRow) {
                    $ok = false;
                    $permanentFailure = true;
                    $errorMsg = 'Abonnement Web Push introuvable ou révoqué.';
                } else {
                    $payloadData = is_array($content['payload'] ?? null) ? $content['payload'] : [
                        'title' => (string) $row['subject'],
                        'body' => '',
                        'url' => '/alerts',
                        'tag' => 'prime-care',
                    ];
                    $urgency = (string) ($content['urgency'] ?? 'normal');
                    $res = WebPushService::sendToSubscription($subRow, $payloadData, $urgency);
                    $ok = $res['ok'];
                    $permanentFailure = $res['expired'];
                    $errorMsg = $res['error'];
                }
            } elseif ($channel === 'sms') {
                $smsRes = SmsChannel::send((string) $row['recipient'], (string) ($row['subject'] ?? ''));
                $ok = $smsRes['ok'];
                $permanentFailure = !SmsChannel::isEnabled();
                $errorMsg = $smsRes['error'];
            }

            if ($ok) {
                $upd = $pdo->prepare(
                    "UPDATE notification_queue
                     SET status = 'sent', attempts = :att, sent_at = :sat, last_error = NULL, updated_at = :u_at
                     WHERE id = :id"
                );
                $upd->execute([
                    'att' => $attempts,
                    'sat' => $nowUtc,
                    'u_at' => $nowUtc,
                    'id' => $qid,
                ]);
                $sent++;
            } elseif ($permanentFailure || $attempts >= $maxAttempts) {
                $upd = $pdo->prepare(
                    "UPDATE notification_queue
                     SET status = 'failed', attempts = :att, last_error = :err, updated_at = :u_at
                     WHERE id = :id"
                );
                $upd->execute([
                    'att' => $attempts,
                    'err' => $errorMsg,
                    'u_at' => $nowUtc,
                    'id' => $qid,
                ]);
                $failed++;
            } else {
                // Backoff exponentiel : 2^attempts minutes
                $delaySec = (int) pow(2, $attempts) * 60;
                $nextTryUtc = gmdate('Y-m-d H:i:s', $nowTs + $delaySec);
                $upd = $pdo->prepare(
                    "UPDATE notification_queue
                     SET status = 'pending', attempts = :att, next_try_at = :next_at, last_error = :err, updated_at = :u_at
                     WHERE id = :id"
                );
                $upd->execute([
                    'att' => $attempts,
                    'next_at' => $nextTryUtc,
                    'err' => $errorMsg,
                    'u_at' => $nowUtc,
                    'id' => $qid,
                ]);
                $retried++;
            }
        }

        return [
            'sent' => $sent,
            'retried' => $retried,
            'failed' => $failed,
        ];
    }

    /**
     * Compte le nombre d'alertes ouvertes (`new` + `seen`) pour le badge de l'en-tête.
     */
    public static function countOpenAlerts(int $patientId = 1): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM alerts
             WHERE patient_id = :pid AND deleted_at IS NULL AND status IN ('new', 'seen')"
        );
        $stmt->execute(['pid' => $patientId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Liste les alertes du centre d'alertes, triées par gravité (`critical` > `warning` > `info`) puis date décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listAlerts(string $filter = 'open', int $patientId = 1, int $limit = 100): array
    {
        $pdo = Database::connection();
        $whereStatus = match ($filter) {
            'handled' => "a.status = 'handled'",
            'all' => '1 = 1',
            default => "a.status IN ('new', 'seen')",
        };

        $sql = "SELECT a.*,
                       hu.first_name AS handled_first_name, hu.last_name AS handled_last_name
                FROM alerts a
                LEFT JOIN users hu ON hu.id = a.handled_by
                WHERE a.patient_id = :pid
                  AND a.deleted_at IS NULL
                  AND {$whereStatus}
                ORDER BY
                  CASE a.level
                    WHEN 'critical' THEN 1
                    WHEN 'warning' THEN 2
                    ELSE 3
                  END ASC,
                  a.created_at DESC,
                  a.id DESC
                LIMIT " . max(1, min(200, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['pid' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $localTz = PlanningService::localTz();
        $utcTz = new DateTimeZone('UTC');

        foreach ($rows as &$r) {
            $cDt = (new DateTimeImmutable((string) $r['created_at'], $utcTz))->setTimezone($localTz);
            $r['local_created_at'] = $cDt->format('d/m/Y à H\hi');
            if (!empty($r['handled_at'])) {
                $hDt = (new DateTimeImmutable((string) $r['handled_at'], $utcTz))->setTimezone($localTz);
                $r['local_handled_at'] = $hDt->format('d/m/Y à H\hi');
            } else {
                $r['local_handled_at'] = null;
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Passe automatiquement les alertes `new` à `seen` à l'ouverture du centre d'alertes.
     */
    public static function markAllNewAsSeen(int $userId, int $patientId = 1): int
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = 'seen', seen_at = :sat, seen_by = :sby, updated_at = :u_at
             WHERE patient_id = :pid AND status = 'new' AND deleted_at IS NULL"
        );
        $stmt->execute([
            'sat' => $now,
            'sby' => $userId,
            'u_at' => $now,
            'pid' => $patientId,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Marque une alerte spécifique comme `seen`.
     */
    public static function markSeen(int $alertId, int $userId, int $patientId = 1): ?array
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = CASE WHEN status = 'new' THEN 'seen' ELSE status END,
                 seen_at = COALESCE(seen_at, :sat),
                 seen_by = COALESCE(seen_by, :sby),
                 updated_at = :u_at
             WHERE id = :id AND patient_id = :pid AND deleted_at IS NULL"
        );
        $stmt->execute([
            'sat' => $now,
            'sby' => $userId,
            'u_at' => $now,
            'id' => $alertId,
            'pid' => $patientId,
        ]);
        return self::findById($alertId);
    }

    /**
     * Traite et clôture une alerte (`status = 'handled'`) avec note de résolution.
     */
    public static function handleAlert(int $alertId, int $userId, string $resolution, int $patientId = 1): array
    {
        $resolution = trim($resolution);
        if ($resolution === '') {
            $resolution = 'Vérifié';
        }

        $pdo = Database::connection();
        $before = self::findById($alertId);
        if ($before === null || (int) $before['patient_id'] !== $patientId) {
            throw new InvalidArgumentException('Alerte introuvable.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE alerts
             SET status = 'handled',
                 open_dedupe_key = NULL,
                 handled_by = :by,
                 handled_at = :hat,
                 resolution = :res,
                 updated_at = :u_at
             WHERE id = :id AND patient_id = :pid"
        );
        $stmt->execute([
            'by' => $userId,
            'hat' => $now,
            'res' => substr($resolution, 0, 255),
            'u_at' => $now,
            'id' => $alertId,
            'pid' => $patientId,
        ]);

        $after = self::findById($alertId);
        AuditService::log('alert.handle', 'alert', $alertId, $before, $after, $userId);
        return $after ?? ['id' => $alertId];
    }

    /**
     * Retourne une alerte par son ID.
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM alerts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, int>
     */
    public static function getUserIdsByRoles(array $roles, int $patientId = 1): array
    {
        if ($roles === []) {
            return [];
        }
        $pdo = Database::connection();
        $placeholders = [];
        $params = ['pid' => $patientId];
        foreach (array_values($roles) as $idx => $role) {
            $key = 'r' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $role;
        }
        $inSql = implode(', ', $placeholders);
        $stmt = $pdo->prepare(
            "SELECT id FROM users
             WHERE patient_id = :pid AND deleted_at IS NULL AND is_active = 1 AND role IN ({$inSql})"
        );
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array<int, int>
     */
    private static function defaultRecipientUserIds(string $type, int $patientId, string $nowUtc): array
    {
        return match ($type) {
            'intake_late' => array_values(array_unique(array_merge(
                self::getOnDutyUserIds($patientId, $nowUtc),
                self::getUserIdsByRoles(['admin'], $patientId)
            ))),
            'measure_late' => self::getOnDutyUserIds($patientId, $nowUtc) ?: self::getUserIdsByRoles(['admin', 'coordinator'], $patientId),
            default => self::getUserIdsByRoles(['admin', 'coordinator'], $patientId),
        };
    }
}
