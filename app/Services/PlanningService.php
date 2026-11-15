<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

/**
 * PlanningService — Gestion du planning d'équipe (E16) :
 * - Modèles de créneaux (shift_templates)
 * - Vue semaine et vue mois avec couleurs par aide-soignante
 * - Contrôle anti-chevauchement par aide-soignante (HTTP 422)
 * - Créneaux non couverts ("Non couvert") et remplacements ("Fatou (remplace Marie)")
 * - Duplication d'une semaine type sur N semaines
 * - Déclaration d'absences et marquage automatique "À remplacer" sur les créneaux impactés
 */
final class PlanningService
{
    public static function localTz(): DateTimeZone
    {
        return new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
    }

    public static function utcTz(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    /**
     * Retourne les modèles de créneaux actifs du patient.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTemplates(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM shift_templates
             WHERE patient_id = :pid AND deleted_at IS NULL AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['pid' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée ou met à jour un modèle de créneau.
     *
     * @param array<string, mixed> $data
     */
    public static function saveTemplate(array $data, int $actorId, int $patientId = 1): int
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $label = trim((string) ($data['label'] ?? ''));
        $startTime = trim((string) ($data['start_time'] ?? '08:00'));
        $endTime = trim((string) ($data['end_time'] ?? '20:00'));
        $colorClass = trim((string) ($data['color_class'] ?? 'day'));

        if ($label === '' || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            throw new InvalidArgumentException('Libellé et horaires (HH:MM) du modèle obligatoires.');
        }

        [$sh, $sm] = array_map('intval', explode(':', $startTime));
        [$eh, $em] = array_map('intval', explode(':', $endTime));
        $startMin = $sh * 60 + $sm;
        $endMin = $eh * 60 + $em;
        $spansNextDay = ($endMin <= $startMin) ? 1 : 0;
        $duration = $spansNextDay ? (1440 - $startMin + $endMin) : ($endMin - $startMin);

        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE shift_templates
                 SET label = :label, start_time = :st, end_time = :et,
                     spans_next_day = :spans, duration_minutes = :dur, color_class = :cc, updated_at = :now
                 WHERE id = :id AND patient_id = :pid AND deleted_at IS NULL'
            );
            $stmt->execute([
                'label' => $label,
                'st' => $startTime,
                'et' => $endTime,
                'spans' => $spansNextDay,
                'dur' => $duration,
                'cc' => $colorClass,
                'now' => $now,
                'id' => $id,
                'pid' => $patientId,
            ]);
            AuditService::log('shift_template.update', 'shift_template', $id, null, $data, $actorId);
            return $id;
        }

        $code = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label)) . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        $stmt = $pdo->prepare(
            'INSERT INTO shift_templates
             (patient_id, code, label, start_time, end_time, spans_next_day, duration_minutes, color_class, is_active, sort_order, created_at, updated_at, created_by)
             VALUES (:pid, :code, :label, :st, :et, :spans, :dur, :cc, 1, 50, :c_at, :u_at, :by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'code' => substr($code, 0, 50),
            'label' => $label,
            'st' => $startTime,
            'et' => $endTime,
            'spans' => $spansNextDay,
            'dur' => $duration,
            'cc' => $colorClass,
            'c_at' => $now,
            'u_at' => $now,
            'by' => $actorId,
        ]);
        $newId = (int) $pdo->lastInsertId();
        AuditService::log('shift_template.create', 'shift_template', $newId, null, $data, $actorId);
        return $newId;
    }

    /**
     * Convertit une date locale (YYYY-MM-DD) et deux heures locales (HH:MM) en couple [start_at_utc, end_at_utc].
     * Si end_time <= start_time, la fin est positionnée au lendemain matin en heure locale.
     *
     * @return array{start_utc: string, end_utc: string}
     */
    public static function localDateTimesToUtc(string $localDate, string $startTime, string $endTime): array
    {
        $localTz = self::localTz();
        $utcTz = self::utcTz();

        $startLocal = new DateTimeImmutable($localDate . ' ' . $startTime . ':00', $localTz);
        $endLocal = new DateTimeImmutable($localDate . ' ' . $endTime . ':00', $localTz);
        if ($endLocal <= $startLocal) {
            $endLocal = $endLocal->modify('+1 day');
        }

        return [
            'start_utc' => $startLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
            'end_utc' => $endLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Vérifie si un créneau [startUtc, endUtc] chevauche un créneau actif existant pour la même aide-soignante.
     * Retourne le créneau en conflit ou null s'il n'y a pas de chevauchement.
     *
     * @return array<string, mixed>|null
     */
    public static function findOverlappingShift(
        int $userId,
        string $startUtc,
        string $endUtc,
        ?int $ignoreShiftId = null,
        int $patientId = 1
    ): ?array {
        $pdo = Database::connection();
        $sql = "SELECT s.*, u.first_name, u.last_name
                FROM shifts s
                LEFT JOIN users u ON u.id = s.user_id
                WHERE s.patient_id = :pid
                  AND s.user_id = :uid
                  AND s.deleted_at IS NULL
                  AND s.status != 'cancelled'
                  AND s.start_at < :end_utc
                  AND s.end_at > :start_utc";
        $params = [
            'pid' => $patientId,
            'uid' => $userId,
            'end_utc' => $endUtc,
            'start_utc' => $startUtc,
        ];
        if ($ignoreShiftId !== null && $ignoreShiftId > 0) {
            $sql .= ' AND s.id != :ignore_id';
            $params['ignore_id'] = $ignoreShiftId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Crée un créneau dans le planning.
     * Lève InvalidArgumentException (422) si deux créneaux se chevauchent pour la même personne.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function createShift(array $data, int $actorId, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $templateId = !empty($data['template_id']) ? (int) $data['template_id'] : null;
        $userId = isset($data['user_id']) && $data['user_id'] !== '' && (int) $data['user_id'] > 0
            ? (int) $data['user_id']
            : null;
        $replacedUserId = isset($data['replaced_user_id']) && $data['replaced_user_id'] !== '' && (int) $data['replaced_user_id'] > 0
            ? (int) $data['replaced_user_id']
            : null;
        $status = in_array($data['status'] ?? 'planned', ['planned', 'confirmed', 'cancelled'], true)
            ? (string) ($data['status'] ?? 'planned')
            : 'planned';
        $notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
            ? trim((string) $data['notes'])
            : null;

        if (!empty($data['start_at']) && !empty($data['end_at'])) {
            $startUtc = (string) $data['start_at'];
            $endUtc = (string) $data['end_at'];
        } else {
            $localDate = trim((string) ($data['date'] ?? $data['start_date'] ?? ''));
            $startTime = trim((string) ($data['start_time'] ?? '08:00'));
            $endTime = trim((string) ($data['end_time'] ?? '20:00'));
            if ($templateId !== null && (empty($data['start_time']) || empty($data['end_time']))) {
                $tStmt = $pdo->prepare('SELECT start_time, end_time FROM shift_templates WHERE id = :id');
                $tStmt->execute(['id' => $templateId]);
                $tpl = $tStmt->fetch(PDO::FETCH_ASSOC);
                if ($tpl) {
                    $startTime = (string) $tpl['start_time'];
                    $endTime = (string) $tpl['end_time'];
                }
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate)) {
                throw new InvalidArgumentException('Date du créneau invalide.');
            }
            $pair = self::localDateTimesToUtc($localDate, $startTime, $endTime);
            $startUtc = $pair['start_utc'];
            $endUtc = $pair['end_utc'];
        }

        if ($endUtc <= $startUtc) {
            throw new InvalidArgumentException('L’heure de fin doit être postérieure à l’heure de début.');
        }

        if ($userId !== null && $status !== 'cancelled') {
            $overlap = self::findOverlappingShift($userId, $startUtc, $endUtc, null, $patientId);
            if ($overlap !== null) {
                $cgName = trim(($overlap['first_name'] ?? '') . ' ' . ($overlap['last_name'] ?? ''));
                throw new InvalidArgumentException(
                    "Chevauchement refusé : {$cgName} a déjà un créneau prévu sur cette plage horaire."
                );
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO shifts
             (patient_id, template_id, user_id, replaced_user_id, start_at, end_at, status, notes, created_at, updated_at, created_by)
             VALUES (:pid, :tid, :uid, :ruid, :start_at, :end_at, :status, :notes, :c_at, :u_at, :by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'tid' => $templateId,
            'uid' => $userId,
            'ruid' => $replacedUserId,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'status' => $status,
            'notes' => $notes,
            'c_at' => $now,
            'u_at' => $now,
            'by' => $actorId,
        ]);

        $shiftId = (int) $pdo->lastInsertId();
        $created = self::findById($shiftId);
        AuditService::log('shift.create', 'shift', $shiftId, null, $created, $actorId);

        if (class_exists(AlertService::class)) {
            AlertService::onShiftSaved($shiftId);
        }

        return $created ?? ['id' => $shiftId];
    }

    /**
     * Met à jour un créneau (horaires, affectation, remplacement, statut, notes).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function updateShift(int $shiftId, array $data, int $actorId, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $before = self::findById($shiftId);
        if ($before === null || (int) $before['patient_id'] !== $patientId) {
            throw new InvalidArgumentException('Créneau introuvable.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $templateId = array_key_exists('template_id', $data)
            ? (!empty($data['template_id']) ? (int) $data['template_id'] : null)
            : ($before['template_id'] !== null ? (int) $before['template_id'] : null);

        $userId = array_key_exists('user_id', $data)
            ? ($data['user_id'] !== '' && $data['user_id'] !== null && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null)
            : ($before['user_id'] !== null ? (int) $before['user_id'] : null);

        // Handle replacement tracking: if `is_replacement` is passed or `replaced_user_id` is passed
        $replacedUserId = $before['replaced_user_id'] !== null ? (int) $before['replaced_user_id'] : null;
        if (array_key_exists('replaced_user_id', $data)) {
            $replacedUserId = ($data['replaced_user_id'] !== '' && $data['replaced_user_id'] !== null && (int) $data['replaced_user_id'] > 0)
                ? (int) $data['replaced_user_id']
                : null;
        } elseif (!empty($data['is_replacement']) && $before['user_id'] !== null && $userId !== null && (int) $before['user_id'] !== $userId) {
            $replacedUserId = (int) $before['user_id'];
        }

        if ($userId !== null && $replacedUserId === $userId) {
            $replacedUserId = null;
        }

        $status = isset($data['status']) && in_array($data['status'], ['planned', 'confirmed', 'cancelled'], true)
            ? (string) $data['status']
            : (string) $before['status'];

        $notes = array_key_exists('notes', $data)
            ? (trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null)
            : $before['notes'];

        if (!empty($data['start_at']) && !empty($data['end_at'])) {
            $startUtc = (string) $data['start_at'];
            $endUtc = (string) $data['end_at'];
        } elseif ((!empty($data['date']) || !empty($data['start_date'])) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $pair = self::localDateTimesToUtc((string) ($data['date'] ?? $data['start_date']), (string) $data['start_time'], (string) $data['end_time']);
            $startUtc = $pair['start_utc'];
            $endUtc = $pair['end_utc'];
        } else {
            $startUtc = (string) $before['start_at'];
            $endUtc = (string) $before['end_at'];
        }

        if ($userId !== null && $status !== 'cancelled') {
            $overlap = self::findOverlappingShift($userId, $startUtc, $endUtc, $shiftId, $patientId);
            if ($overlap !== null) {
                $cgName = trim(($overlap['first_name'] ?? '') . ' ' . ($overlap['last_name'] ?? ''));
                throw new InvalidArgumentException(
                    "Chevauchement refusé : {$cgName} a déjà un créneau prévu sur cette plage horaire."
                );
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE shifts
             SET template_id = :tid, user_id = :uid, replaced_user_id = :ruid,
                 start_at = :start_at, end_at = :end_at, status = :status, notes = :notes, updated_at = :now
             WHERE id = :id AND patient_id = :pid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'tid' => $templateId,
            'uid' => $userId,
            'ruid' => $replacedUserId,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'status' => $status,
            'notes' => $notes,
            'now' => $now,
            'id' => $shiftId,
            'pid' => $patientId,
        ]);

        $after = self::findById($shiftId);
        AuditService::log('shift.update', 'shift', $shiftId, $before, $after, $actorId);

        if (class_exists(AlertService::class)) {
            AlertService::onShiftSaved($shiftId);
        }

        return $after ?? ['id' => $shiftId];
    }

    /**
     * Supprime logiquement un créneau.
     */
    public static function deleteShift(int $shiftId, int $actorId, int $patientId = 1): void
    {
        $pdo = Database::connection();
        $before = self::findById($shiftId);
        if ($before === null) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE shifts SET status = 'cancelled', deleted_at = :d_at, updated_at = :u_at
             WHERE id = :id AND patient_id = :pid"
        );
        $stmt->execute([
            'd_at' => $now,
            'u_at' => $now,
            'id' => $shiftId,
            'pid' => $patientId,
        ]);
        AuditService::log('shift.delete', 'shift', $shiftId, $before, ['deleted_at' => $now], $actorId);
    }

    /**
     * Duplique tous les créneaux d'une semaine source (lundi YYYY-MM-DD) sur les N semaines suivantes.
     * Conserve les heures locales (Europe/Paris) pour gérer proprement les changements d'heure.
     *
     * @return array{created: int, skipped: int}
     */
    public static function duplicateWeek(string $mondayDate, int $weeksCount, int $actorId, int $patientId = 1): array
    {
        $weeksCount = max(1, min(12, $weeksCount));
        $localTz = self::localTz();
        $utcTz = self::utcTz();

        $weekStartLocal = new DateTimeImmutable($mondayDate . ' 00:00:00', $localTz);
        // Normalize to Monday if another day was passed
        $isoDow = (int) $weekStartLocal->format('N');
        if ($isoDow !== 1) {
            $weekStartLocal = $weekStartLocal->modify('-' . ($isoDow - 1) . ' days');
        }
        $weekEndLocal = $weekStartLocal->modify('+7 days');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT * FROM shifts
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status != 'cancelled'
               AND start_at >= :wstart
               AND start_at < :wend
             ORDER BY start_at ASC"
        );
        $stmt->execute([
            'pid' => $patientId,
            'wstart' => $weekStartLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
            'wend' => $weekEndLocal->setTimezone($utcTz)->format('Y-m-d H:i:s'),
        ]);
        $sourceShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $createdCount = 0;
        $skippedCount = 0;

        for ($w = 1; $w <= $weeksCount; $w++) {
            foreach ($sourceShifts as $src) {
                $srcStartLocal = (new DateTimeImmutable((string) $src['start_at'], $utcTz))->setTimezone($localTz);
                $srcEndLocal = (new DateTimeImmutable((string) $src['end_at'], $utcTz))->setTimezone($localTz);

                $newStartLocal = $srcStartLocal->modify("+{$w} weeks");
                $newEndLocal = $srcEndLocal->modify("+{$w} weeks");

                $newStartUtc = $newStartLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');
                $newEndUtc = $newEndLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');

                $uid = $src['user_id'] !== null ? (int) $src['user_id'] : null;

                if ($uid !== null) {
                    $overlap = self::findOverlappingShift($uid, $newStartUtc, $newEndUtc, null, $patientId);
                    if ($overlap !== null) {
                        $skippedCount++;
                        continue;
                    }
                } else {
                    // Check if identical uncovered shift already exists
                    $chk = $pdo->prepare(
                        "SELECT id FROM shifts
                         WHERE patient_id = :pid AND user_id IS NULL AND deleted_at IS NULL
                           AND status != 'cancelled' AND start_at = :st AND end_at = :et LIMIT 1"
                    );
                    $chk->execute(['pid' => $patientId, 'st' => $newStartUtc, 'et' => $newEndUtc]);
                    if ($chk->fetchColumn()) {
                        $skippedCount++;
                        continue;
                    }
                }

                self::createShift([
                    'template_id' => $src['template_id'],
                    'user_id' => $uid,
                    'start_at' => $newStartUtc,
                    'end_at' => $newEndUtc,
                    'status' => 'planned',
                    'notes' => $src['notes'],
                ], $actorId, $patientId);
                $createdCount++;
            }
        }

        return [
            'created' => $createdCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Enregistre une absence (congé, maladie, autre).
     *
     * @param array<string, mixed> $data
     */
    public static function createAbsence(array $data, int $actorId, int $patientId = 1): int
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $userId = (int) ($data['user_id'] ?? 0);
        $rawType = (string) ($data['absence_type'] ?? $data['reason'] ?? 'conge');
        $type = in_array($rawType, ['conge', 'maladie', 'autre'], true)
            ? $rawType
            : 'conge';
        $startDate = trim((string) ($data['start_date'] ?? ''));
        $endDate = trim((string) ($data['end_date'] ?? ''));
        $rawComment = (string) ($data['comment'] ?? $data['notes'] ?? '');
        $comment = trim($rawComment) !== '' ? trim($rawComment) : null;

        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new InvalidArgumentException('Salariée et dates de début/fin d’absence obligatoires.');
        }
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('La date de fin d’absence doit être postérieure ou égale à la date de début.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO absences
             (patient_id, user_id, absence_type, start_date, end_date, comment, created_at, updated_at, created_by)
             VALUES (:pid, :uid, :type, :sd, :ed, :comment, :c_at, :u_at, :by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'type' => $type,
            'sd' => $startDate,
            'ed' => $endDate,
            'comment' => $comment,
            'c_at' => $now,
            'u_at' => $now,
            'by' => $actorId,
        ]);

        $id = (int) $pdo->lastInsertId();
        AuditService::log('absence.create', 'absence', $id, null, $data, $actorId);
        return $id;
    }

    /**
     * Supprime logiquement une absence.
     */
    public static function deleteAbsence(int $absenceId, int $actorId, int $patientId = 1): void
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE absences SET deleted_at = :d_at, updated_at = :u_at WHERE id = :id AND patient_id = :pid'
        );
        $stmt->execute([
            'd_at' => $now,
            'u_at' => $now,
            'id' => $absenceId,
            'pid' => $patientId,
        ]);
        AuditService::log('absence.delete', 'absence', $absenceId, null, ['deleted_at' => $now], $actorId);
    }

    /**
     * Récupère les absences actives sur une plage de dates locales [startDate, endDate].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAbsencesForRange(string $startDate, string $endDate, ?int $onlyUserId = null, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT a.*, u.first_name, u.last_name, u.color_hex
                FROM absences a
                INNER JOIN users u ON u.id = a.user_id
                WHERE a.patient_id = :pid
                  AND a.deleted_at IS NULL
                  AND a.start_date <= :ed
                  AND a.end_date >= :sd';
        $params = [
            'pid' => $patientId,
            'ed' => $endDate,
            'sd' => $startDate,
        ];
        if ($onlyUserId !== null) {
            $sql .= ' AND a.user_id = :uid';
            $params['uid'] = $onlyUserId;
        }
        $sql .= ' ORDER BY a.start_date ASC, a.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les créneaux d'une plage locale [startDate 00:00, endDate 23:59:59],
     * enrichis avec les informations de l'aide-soignante, de la personne remplacée, et le marqueur `needs_replacement`
     * si une absence déclarée couvre la date locale du créneau.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getShiftsForLocalRange(
        string $startDate,
        string $endDate,
        ?int $onlyUserId = null,
        int $patientId = 1
    ): array {
        $pdo = Database::connection();
        $localTz = self::localTz();
        $utcTz = self::utcTz();

        $startLocal = new DateTimeImmutable($startDate . ' 00:00:00', $localTz);
        $endLocal = new DateTimeImmutable($endDate . ' 23:59:59', $localTz);

        $startUtc = $startLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $endUtc = $endLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');

        $sql = "SELECT s.*,
                       t.label AS template_label, t.color_class AS template_color,
                       u.first_name, u.last_name, u.role AS user_role, u.color_hex,
                       ru.first_name AS replaced_first_name, ru.last_name AS replaced_last_name
                FROM shifts s
                LEFT JOIN shift_templates t ON t.id = s.template_id
                LEFT JOIN users u ON u.id = s.user_id
                LEFT JOIN users ru ON ru.id = s.replaced_user_id
                WHERE s.patient_id = :pid
                  AND s.deleted_at IS NULL
                  AND s.status != 'cancelled'
                  AND s.start_at >= :start_utc
                  AND s.start_at <= :end_utc";
        $params = [
            'pid' => $patientId,
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
        ];
        if ($onlyUserId !== null) {
            $sql .= ' AND s.user_id = :uid';
            $params['uid'] = $onlyUserId;
        }
        $sql .= ' ORDER BY s.start_at ASC, s.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $absences = self::getAbsencesForRange($startDate, $endDate, $onlyUserId, $patientId);

        foreach ($shifts as &$shift) {
            $stLocal = (new DateTimeImmutable((string) $shift['start_at'], $utcTz))->setTimezone($localTz);
            $etLocal = (new DateTimeImmutable((string) $shift['end_at'], $utcTz))->setTimezone($localTz);

            $shift['local_date'] = $stLocal->format('Y-m-d');
            $shift['local_start_time'] = $stLocal->format('H:i');
            $shift['local_end_time'] = $etLocal->format('H:i');
            $shift['spans_next_day'] = $etLocal->format('Y-m-d') !== $stLocal->format('Y-m-d');
            $shift['duration_hours'] = round(($etLocal->getTimestamp() - $stLocal->getTimestamp()) / 3600, 2);
            $shift['is_uncovered'] = ($shift['user_id'] === null);
            $shift['needs_replacement'] = false;
            $shift['absence_info'] = null;

            if ($shift['user_id'] !== null) {
                $uid = (int) $shift['user_id'];
                $sDate = $shift['local_date'];
                foreach ($absences as $abs) {
                    if ((int) $abs['user_id'] === $uid && $sDate >= $abs['start_date'] && $sDate <= $abs['end_date']) {
                        $shift['needs_replacement'] = true;
                        $shift['absence_info'] = $abs;
                        break;
                    }
                }
            }
        }
        unset($shift);

        return $shifts;
    }

    /**
     * Retourne un créneau par son ID avec les coordonnées locales.
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $shiftId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT s.*,
                    t.label AS template_label,
                    u.first_name, u.last_name, u.color_hex,
                    ru.first_name AS replaced_first_name, ru.last_name AS replaced_last_name
             FROM shifts s
             LEFT JOIN shift_templates t ON t.id = s.template_id
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN users ru ON ru.id = s.replaced_user_id
             WHERE s.id = :id AND s.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $shiftId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $localTz = self::localTz();
        $utcTz = self::utcTz();
        $stLocal = (new DateTimeImmutable((string) $row['start_at'], $utcTz))->setTimezone($localTz);
        $etLocal = (new DateTimeImmutable((string) $row['end_at'], $utcTz))->setTimezone($localTz);
        $row['local_date'] = $stLocal->format('Y-m-d');
        $row['local_start_time'] = $stLocal->format('H:i');
        $row['local_end_time'] = $etLocal->format('H:i');

        return $row;
    }

    /**
     * Retourne le créneau en cours ou le prochain créneau à venir pour une aide-soignante ("Mon prochain créneau").
     *
     * @return array<string, mixed>|null
     */
    public static function getNextOrCurrentShiftForUser(int $userId, int $patientId = 1, ?string $nowUtc = null): ?array
    {
        $pdo = Database::connection();
        $nowUtc ??= gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "SELECT s.*,
                    t.label AS template_label,
                    ru.first_name AS replaced_first_name, ru.last_name AS replaced_last_name
             FROM shifts s
             LEFT JOIN shift_templates t ON t.id = s.template_id
             LEFT JOIN users ru ON ru.id = s.replaced_user_id
             WHERE s.patient_id = :pid
               AND s.user_id = :uid
               AND s.deleted_at IS NULL
               AND s.status != 'cancelled'
               AND s.end_at >= :now_utc
             ORDER BY s.start_at ASC
             LIMIT 1"
        );
        $stmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'now_utc' => $nowUtc,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $localTz = self::localTz();
        $utcTz = self::utcTz();
        $stLocal = (new DateTimeImmutable((string) $row['start_at'], $utcTz))->setTimezone($localTz);
        $etLocal = (new DateTimeImmutable((string) $row['end_at'], $utcTz))->setTimezone($localTz);

        $row['local_date'] = $stLocal->format('Y-m-d');
        $row['local_start_time'] = $stLocal->format('H:i');
        $row['local_end_time'] = $etLocal->format('H:i');
        $row['is_active_now'] = ((string) $row['start_at'] <= $nowUtc && (string) $row['end_at'] >= $nowUtc);

        return $row;
    }

    /**
     * Retourne les membres de l'équipe pouvant être affectés aux créneaux (`caregiver`, `coordinator`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getTeamMembers(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id, first_name, last_name, role, color_hex, contract_type, weekly_hours
             FROM users
             WHERE patient_id = :pid AND deleted_at IS NULL AND is_active = 1 AND role IN ('caregiver', 'coordinator')
             ORDER BY role DESC, first_name ASC"
        );
        $stmt->execute(['pid' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
