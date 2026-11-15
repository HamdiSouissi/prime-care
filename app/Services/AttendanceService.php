<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

/**
 * AttendanceService — Pointage des aides-soignantes, preuve de présence GPS / QR code HMAC,
 * oubli de pointage déclaré, contrôle hebdomadaire et option "Saisie autorisée uniquement si pointée" (E05, E17).
 */
final class AttendanceService
{
    public const SETTING_KEY = 'attendance_config';

    /**
     * Calcule la distance Haversine en mètres entre deux coordonnées GPS (WGS84).
     */
    public static function haversineDistanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): int {
        $earthRadiusM = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        return (int) round($earthRadiusM * $c);
    }

    /**
     * Retourne les réglages de pointage et les coordonnées du domicile patient.
     *
     * @return array{
     *   verification_mode: string,
     *   radius_m: int,
     *   require_clocked_in: bool,
     *   home_latitude: float,
     *   home_longitude: float
     * }
     */
    public static function getSettings(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $pStmt = $pdo->prepare('SELECT home_latitude, home_longitude, home_radius_m FROM patients WHERE id = :id LIMIT 1');
        $pStmt->execute(['id' => $patientId]);
        $patient = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $saved = Setting::get(self::SETTING_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $mode = (string) ($saved['verification_mode'] ?? 'gps');
        if (!in_array($mode, ['gps', 'qr', 'both', 'none'], true)) {
            $mode = 'gps';
        }

        $radius = isset($saved['radius_m'])
            ? (int) $saved['radius_m']
            : (int) ($patient['home_radius_m'] ?? 150);
        $radius = max(30, min(2000, $radius));

        $homeLat = isset($saved['home_latitude'])
            ? (float) $saved['home_latitude']
            : (float) ($patient['home_latitude'] ?? 48.7903600);
        $homeLon = isset($saved['home_longitude'])
            ? (float) $saved['home_longitude']
            : (float) ($patient['home_longitude'] ?? 2.4555700);

        return [
            'verification_mode' => $mode,
            'radius_m' => $radius,
            'require_clocked_in' => !empty($saved['require_clocked_in']),
            'home_latitude' => $homeLat,
            'home_longitude' => $homeLon,
        ];
    }

    /**
     * Met à jour les réglages de pointage dans `settings` et `patients`.
     *
     * @param array<string, mixed> $input
     */
    public static function updateSettings(array $input, int $actorId, int $patientId = 1): void
    {
        $current = self::getSettings($patientId);
        $mode = (string) ($input['verification_mode'] ?? $current['verification_mode']);
        if (!in_array($mode, ['gps', 'qr', 'both', 'none'], true)) {
            $mode = 'gps';
        }

        $radius = isset($input['radius_m']) ? (int) $input['radius_m'] : $current['radius_m'];
        $radius = max(30, min(2000, $radius));

        $requireClockedIn = !empty($input['require_clocked_in']);
        $homeLat = isset($input['home_latitude']) && $input['home_latitude'] !== ''
            ? (float) str_replace(',', '.', (string) $input['home_latitude'])
            : $current['home_latitude'];
        $homeLon = isset($input['home_longitude']) && $input['home_longitude'] !== ''
            ? (float) str_replace(',', '.', (string) $input['home_longitude'])
            : $current['home_longitude'];

        $newConfig = [
            'verification_mode' => $mode,
            'radius_m' => $radius,
            'require_clocked_in' => $requireClockedIn,
            'home_latitude' => $homeLat,
            'home_longitude' => $homeLon,
        ];

        Setting::set(self::SETTING_KEY, $newConfig, $actorId);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE patients
             SET home_latitude = :lat, home_longitude = :lon, home_radius_m = :rad, updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'lat' => $homeLat,
            'lon' => $homeLon,
            'rad' => $radius,
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $patientId,
        ]);

        AuditService::log('settings.attendance', 'setting', $patientId, $current, $newConfig, $actorId);
    }

    /**
     * Génère (et révoque les précédents) un jeton QR code du domicile signé HMAC.
     *
     * @return array<string, mixed>
     */
    public static function regenerateQrToken(int $actorId, int $patientId = 1, string $label = 'Domicile M. Henri Benali'): array
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        // Revoke previous active tokens
        $revStmt = $pdo->prepare(
            'UPDATE attendance_qr_tokens
             SET revoked_at = :r_at, updated_at = :u_at
             WHERE patient_id = :pid AND revoked_at IS NULL'
        );
        $revStmt->execute([
            'r_at' => $now,
            'u_at' => $now,
            'pid' => $patientId,
        ]);

        $tokenId = sprintf(
            '%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(4))
        );
        $rawSecret = bin2hex(random_bytes(16));
        $secretHash = hash('sha256', $rawSecret);
        $hmacSig = hash_hmac('sha256', $tokenId . ':' . $rawSecret, self::hmacKey());
        $qrPayload = 'PRIMECARE-HOME:' . $tokenId . ':' . $rawSecret . ':' . substr($hmacSig, 0, 16);

        $ins = $pdo->prepare(
            'INSERT INTO attendance_qr_tokens
             (patient_id, token_id, secret_hash, qr_payload, label, revoked_at, created_at, updated_at, created_by)
             VALUES (:pid, :tid, :sh, :payload, :label, NULL, :c_at, :u_at, :by)'
        );
        $ins->execute([
            'pid' => $patientId,
            'tid' => $tokenId,
            'sh' => $secretHash,
            'payload' => $qrPayload,
            'label' => $label,
            'c_at' => $now,
            'u_at' => $now,
            'by' => $actorId,
        ]);

        $id = (int) $pdo->lastInsertId();
        AuditService::log('attendance_qr.regenerate', 'attendance_qr_token', $id, null, ['token_id' => $tokenId], $actorId);

        return [
            'id' => $id,
            'token_id' => $tokenId,
            'qr_payload' => $qrPayload,
            'label' => $label,
            'created_at' => $now,
        ];
    }

    /**
     * Retourne le jeton QR actif du domicile (ou en crée un s'il n'en existe aucun).
     *
     * @return array<string, mixed>
     */
    public static function getActiveQrToken(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM attendance_qr_tokens
             WHERE patient_id = :pid AND revoked_at IS NULL AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['pid' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
        return self::regenerateQrToken(1, $patientId);
    }

    /**
     * Vérifie un code QR scanné par rapport au jeton actif non révoqué.
     */
    public static function verifyQrPayload(string $payload, int $patientId = 1): bool
    {
        $payload = trim($payload);
        if (!str_starts_with($payload, 'PRIMECARE-HOME:')) {
            return false;
        }
        $parts = explode(':', $payload);
        if (count($parts) !== 4) {
            return false;
        }
        [, $tokenId, $rawSecret, $shortHmac] = $parts;
        if ($tokenId === '' || $rawSecret === '' || $shortHmac === '') {
            return false;
        }

        $expectedHmac = substr(hash_hmac('sha256', $tokenId . ':' . $rawSecret, self::hmacKey()), 0, 16);
        if (!hash_equals($expectedHmac, $shortHmac)) {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT secret_hash, revoked_at FROM attendance_qr_tokens
             WHERE patient_id = :pid AND token_id = :tid AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'pid' => $patientId,
            'tid' => $tokenId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $row['revoked_at'] !== null) {
            return false;
        }

        $candidateHash = hash('sha256', $rawSecret);
        return hash_equals((string) $row['secret_hash'], $candidateHash);
    }

    /**
     * Retourne l'état actuel de pointage d'un utilisateur :
     * - s'il est actuellement pointé en poste (`is_clocked_in`)
     * - son dernier pointage d'entrée ouvert (`active_in`)
     * - le créneau associé ou prochain (`shift`)
     * - s'il a accepté la notice RGPD (`gdpr_accepted`)
     *
     * @return array<string, mixed>
     */
    public static function getUserClockState(int $userId, int $patientId = 1): array
    {
        $pdo = Database::connection();

        $uStmt = $pdo->prepare('SELECT attendance_gdpr_accepted_at FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute(['id' => $userId]);
        $gdprAt = $uStmt->fetchColumn();

        // Check most recent non-rejected attendance in the last 20 hours
        $cutoff = gmdate('Y-m-d H:i:s', time() - 20 * 3600);
        $attStmt = $pdo->prepare(
            "SELECT * FROM attendances
             WHERE patient_id = :pid
               AND user_id = :uid
               AND deleted_at IS NULL
               AND status != 'rejected'
               AND COALESCE(declared_at, recorded_at) >= :cutoff
             ORDER BY COALESCE(declared_at, recorded_at) DESC, id DESC
             LIMIT 1"
        );
        $attStmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'cutoff' => $cutoff,
        ]);
        $lastAtt = $attStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $isClockedIn = ($lastAtt !== null && (string) $lastAtt['type'] === 'in');
        $nextShift = PlanningService::getNextOrCurrentShiftForUser($userId, $patientId);
        $settings = self::getSettings($patientId);

        return [
            'is_clocked_in' => $isClockedIn,
            'last_attendance' => $lastAtt,
            'next_type' => $isClockedIn ? 'out' : 'in',
            'next_shift' => $nextShift,
            'gdpr_accepted' => ($gdprAt !== false && $gdprAt !== null),
            'gdpr_accepted_at' => $gdprAt ?: null,
            'settings' => $settings,
        ];
    }

    /**
     * Vérifie si un utilisateur est actuellement pointé en poste ("in" valide ou en attente).
     */
    public static function isUserClockedIn(int $userId, int $patientId = 1): bool
    {
        $state = self::getUserClockState($userId, $patientId);
        return (bool) $state['is_clocked_in'];
    }

    /**
     * Vérifie si la saisie médicale est autorisée pour l'utilisateur courant selon le réglage `require_clocked_in`.
     * Si `require_clocked_in` est actif et que l'utilisateur est `caregiver` non pointé, retourne false.
     *
     * @param array<string, mixed>|null $user
     */
    public static function canUserWriteMedical(?array $user, int $patientId = 1): bool
    {
        if ($user === null) {
            return false;
        }
        $role = (string) ($user['role'] ?? '');
        if ($role === 'admin' || $role === 'coordinator') {
            return true;
        }
        if ($role !== 'caregiver') {
            return false;
        }

        $settings = self::getSettings($patientId);
        if (!$settings['require_clocked_in']) {
            return true;
        }

        return self::isUserClockedIn((int) $user['id'], $patientId);
    }

    /**
     * Vérifie si l'utilisateur a validé la mention d'information RGPD relative au pointage.
     */
    public static function hasAcceptedGdprNotice(int $userId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT attendance_gdpr_accepted_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null && trim((string) $val) !== '';
    }

    /**
     * Enregistre l'acceptation de la notice d'information RGPD pour le pointage.
     */
    public static function acceptGdprNotice(int $userId): void
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'UPDATE users SET attendance_gdpr_accepted_at = COALESCE(attendance_gdpr_accepted_at, :now), updated_at = :u_now
             WHERE id = :id'
        );
        $stmt->execute([
            'now' => $now,
            'u_now' => $now,
            'id' => $userId,
        ]);
        AuditService::log('attendance.gdpr_accepted', 'user', $userId, null, ['accepted_at' => $now], $userId);
    }

    /**
     * Alias de `recordPunch` pour compatibilité avec l'API `AttendanceService::punch`.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function punch(
        int $userId,
        array $input,
        string $ipAddress = '127.0.0.1',
        int $patientId = 1,
        ?string $overrideServerUtc = null
    ): array {
        return self::recordPunch($userId, $input, $ipAddress, $patientId, $overrideServerUtc);
    }

    /**
     * Enregistre un pointage en temps réel (bouton glissant / confirmation accessible ou scan QR code).
     * L'heure `recorded_at` est TOUJOURS l'heure du serveur UTC.
     * Vérifie obligatoirement que `attendance_gdpr_accepted_at !== null` (sinon RuntimeException 403 GDPR_NOTICE_REQUIRED).
     * Ne bloque jamais l'aide-soignante pour le GPS : en cas de position hors rayon ou refusée, le pointage est créé en `pending`.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function recordPunch(
        int $userId,
        array $input,
        string $ipAddress = '127.0.0.1',
        int $patientId = 1,
        ?string $overrideServerUtc = null
    ): array {
        if (!self::hasAcceptedGdprNotice($userId)) {
            throw new \RuntimeException(
                'Vous devez prendre connaissance de la mention d’information RGPD avant votre premier pointage.',
                403
            );
        }

        $pdo = Database::connection();
        $serverNowUtc = $overrideServerUtc ?? gmdate('Y-m-d H:i:s');

        $state = self::getUserClockState($userId, $patientId);
        $type = isset($input['type']) && in_array($input['type'], ['in', 'out'], true)
            ? (string) $input['type']
            : (string) $state['next_type'];

        $method = isset($input['method']) && in_array($input['method'], ['slide', 'qr'], true)
            ? (string) $input['method']
            : 'slide';

        $settings = self::getSettings($patientId);
        $vMode = $settings['verification_mode'];

        $lat = isset($input['latitude']) && $input['latitude'] !== '' && $input['latitude'] !== null
            ? (float) $input['latitude']
            : null;
        $lon = isset($input['longitude']) && $input['longitude'] !== '' && $input['longitude'] !== null
            ? (float) $input['longitude']
            : null;
        $acc = isset($input['accuracy_m']) && $input['accuracy_m'] !== '' && $input['accuracy_m'] !== null
            ? (float) $input['accuracy_m']
            : null;
        $qrPayload = trim((string) ($input['qr_payload'] ?? ''));

        $distanceM = null;
        if ($lat !== null && $lon !== null) {
            $distanceM = self::haversineDistanceMeters(
                $lat,
                $lon,
                $settings['home_latitude'],
                $settings['home_longitude']
            );
        }

        $status = 'valid';
        $reasons = [];

        // Check QR code if method == 'qr' or verification_mode requires QR
        $qrValid = false;
        if ($qrPayload !== '') {
            $qrValid = self::verifyQrPayload($qrPayload, $patientId);
            if ($qrValid) {
                $method = 'qr';
            } else {
                throw new InvalidArgumentException('QR code du domicile invalide ou révoqué.');
            }
        } elseif ($method === 'qr') {
            throw new InvalidArgumentException('QR code du domicile requis pour ce mode de pointage.');
        }

        if ($vMode === 'qr' && !$qrValid) {
            $status = 'pending';
            $reasons[] = 'QR code non scanné';
        } elseif ($vMode === 'both') {
            if (!$qrValid) {
                $status = 'pending';
                $reasons[] = 'QR code non scanné';
            }
            if ($distanceM === null) {
                $status = 'pending';
                $reasons[] = 'Position refusée';
            } elseif ($distanceM > $settings['radius_m']) {
                $status = 'pending';
                $reasons[] = "Hors du domicile ({$distanceM} m)";
            }
        } elseif ($vMode === 'gps') {
            // If they punched via valid QR code, accept it; otherwise verify GPS
            if (!$qrValid) {
                if ($distanceM === null) {
                    $status = 'pending';
                    $reasons[] = 'Position refusée';
                } elseif ($distanceM > $settings['radius_m']) {
                    $status = 'pending';
                    $reasons[] = "Hors du domicile ({$distanceM} m)";
                }
            }
        }

        $reasonStr = $reasons !== [] ? implode(' · ', $reasons) : null;

        // Match closest shift for this user around serverNowUtc
        $shiftId = isset($input['shift_id']) && (int) $input['shift_id'] > 0
            ? (int) $input['shift_id']
            : self::findMatchingShiftId($userId, $type, $serverNowUtc, $patientId);

        $stmt = $pdo->prepare(
            'INSERT INTO attendances
             (patient_id, user_id, shift_id, type, recorded_at, declared_at, method,
              latitude, longitude, accuracy_m, distance_m, ip, status, reason, created_at, updated_at, created_by)
             VALUES
             (:pid, :uid, :sid, :type, :rec_at, NULL, :method,
              :lat, :lon, :acc, :dist, :ip, :status, :reason, :c_at, :u_at, :by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'sid' => $shiftId,
            'type' => $type,
            'rec_at' => $serverNowUtc,
            'method' => $method,
            'lat' => $lat,
            'lon' => $lon,
            'acc' => $acc,
            'dist' => $distanceM,
            'ip' => substr($ipAddress, 0, 45),
            'status' => $status,
            'reason' => $reasonStr,
            'c_at' => $serverNowUtc,
            'u_at' => $serverNowUtc,
            'by' => $userId,
        ]);

        $attId = (int) $pdo->lastInsertId();
        $created = self::findById($attId);
        AuditService::log('attendance.punch_' . $type, 'attendance', $attId, null, $created, $userId);

        if (class_exists(AlertService::class)) {
            AlertService::onAttendanceRecorded($created ?? ['id' => $attId]);
        }

        return $created ?? ['id' => $attId];
    }

    /**
     * Déclare un oubli de pointage ("J'ai oublié de pointer") avec heure réelle déclarée et motif obligatoire.
     * Toujours enregistré en statut `pending` + alerte au responsable.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function declareForgotPunch(
        int $userId,
        array $input,
        string $ipAddress = '127.0.0.1',
        int $patientId = 1
    ): array {
        if (!self::hasAcceptedGdprNotice($userId)) {
            throw new \RuntimeException(
                'Vous devez prendre connaissance de la mention d’information RGPD avant votre premier pointage.',
                403
            );
        }

        $pdo = Database::connection();
        $serverNowUtc = gmdate('Y-m-d H:i:s');

        $type = isset($input['type']) && in_array($input['type'], ['in', 'out'], true)
            ? (string) $input['type']
            : 'in';
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Le motif de l’oubli de pointage est obligatoire.');
        }

        $localTz = PlanningService::localTz();
        $utcTz = PlanningService::utcTz();

        if (!empty($input['declared_at_utc'])) {
            $declaredUtc = (string) $input['declared_at_utc'];
        } else {
            $dateStr = trim((string) ($input['declared_date'] ?? (new DateTimeImmutable('now', $localTz))->format('Y-m-d')));
            $timeStr = trim((string) ($input['declared_time'] ?? '08:00'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || !preg_match('/^\d{2}:\d{2}/', $timeStr)) {
                throw new InvalidArgumentException('Date et heure réelles de l’oubli obligatoires.');
            }
            $localDt = new DateTimeImmutable($dateStr . ' ' . substr($timeStr, 0, 5) . ':00', $localTz);
            $declaredUtc = $localDt->setTimezone($utcTz)->format('Y-m-d H:i:s');
        }

        $shiftId = isset($input['shift_id']) && (int) $input['shift_id'] > 0
            ? (int) $input['shift_id']
            : self::findMatchingShiftId($userId, $type, $declaredUtc, $patientId);

        $stmt = $pdo->prepare(
            "INSERT INTO attendances
             (patient_id, user_id, shift_id, type, recorded_at, declared_at, method,
              ip, status, reason, created_at, updated_at, created_by)
             VALUES
             (:pid, :uid, :sid, :type, :rec_at, :dec_at, 'declared',
              :ip, 'pending', :reason, :c_at, :u_at, :by)"
        );
        $stmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'sid' => $shiftId,
            'type' => $type,
            'rec_at' => $serverNowUtc,
            'dec_at' => $declaredUtc,
            'ip' => substr($ipAddress, 0, 45),
            'reason' => $reason,
            'c_at' => $serverNowUtc,
            'u_at' => $serverNowUtc,
            'by' => $userId,
        ]);

        $attId = (int) $pdo->lastInsertId();
        $created = self::findById($attId);
        AuditService::log('attendance.declared', 'attendance', $attId, null, $created, $userId);

        if (class_exists(AlertService::class)) {
            AlertService::onAttendanceRecorded($created ?? ['id' => $attId]);
        }

        return $created ?? ['id' => $attId];
    }

    /**
     * Valide un pointage en attente (`pending` -> `valid`).
     *
     * @return array<string, mixed>
     */
    public static function validateAttendance(int $attendanceId, int $validatorId, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $before = self::findById($attendanceId);
        if ($before === null || (int) $before['patient_id'] !== $patientId) {
            throw new InvalidArgumentException('Pointage introuvable.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE attendances
             SET status = 'valid', validated_by = :vby, validated_at = :vat, updated_at = :u_at
             WHERE id = :id AND patient_id = :pid"
        );
        $stmt->execute([
            'vby' => $validatorId,
            'vat' => $now,
            'u_at' => $now,
            'id' => $attendanceId,
            'pid' => $patientId,
        ]);

        $after = self::findById($attendanceId);
        AuditService::log('attendance.validate', 'attendance', $attendanceId, $before, $after, $validatorId);

        if (class_exists(AlertService::class)) {
            AlertService::onAttendanceValidatedOrRejected($attendanceId, $validatorId, 'Pointage validé');
        }

        return $after ?? ['id' => $attendanceId];
    }

    /**
     * Refuse un pointage en attente avec motif obligatoire (`pending` -> `rejected`).
     *
     * @return array<string, mixed>
     */
    public static function rejectAttendance(int $attendanceId, string $rejectionReason, int $validatorId, int $patientId = 1): array
    {
        $rejectionReason = trim($rejectionReason);
        if ($rejectionReason === '') {
            throw new InvalidArgumentException('Le motif du refus est obligatoire.');
        }

        $pdo = Database::connection();
        $before = self::findById($attendanceId);
        if ($before === null || (int) $before['patient_id'] !== $patientId) {
            throw new InvalidArgumentException('Pointage introuvable.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE attendances
             SET status = 'rejected', rejection_reason = :rreason, validated_by = :vby, validated_at = :vat, updated_at = :u_at
             WHERE id = :id AND patient_id = :pid"
        );
        $stmt->execute([
            'rreason' => $rejectionReason,
            'vby' => $validatorId,
            'vat' => $now,
            'u_at' => $now,
            'id' => $attendanceId,
            'pid' => $patientId,
        ]);

        $after = self::findById($attendanceId);
        AuditService::log('attendance.reject', 'attendance', $attendanceId, $before, $after, $validatorId);

        if (class_exists(AlertService::class)) {
            AlertService::onAttendanceValidatedOrRejected($attendanceId, $validatorId, 'Pointage refusé : ' . $rejectionReason);
        }

        return $after ?? ['id' => $attendanceId];
    }

    /**
     * Retourne la liste des pointages en attente de validation (`status = 'pending'`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getPendingAttendances(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT a.*, u.first_name, u.last_name, u.role AS user_role, u.color_hex,
                    s.start_at AS shift_start_at, s.end_at AS shift_end_at
             FROM attendances a
             INNER JOIN users u ON u.id = a.user_id
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE a.patient_id = :pid AND a.deleted_at IS NULL AND a.status = 'pending'
             ORDER BY COALESCE(a.declared_at, a.recorded_at) DESC, a.id DESC"
        );
        $stmt->execute(['pid' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $localTz = PlanningService::localTz();
        $utcTz = PlanningService::utcTz();

        foreach ($rows as &$r) {
            $effStr = (string) ($r['declared_at'] ?? $r['recorded_at']);
            $effLocal = (new DateTimeImmutable($effStr, $utcTz))->setTimezone($localTz);
            $recLocal = (new DateTimeImmutable((string) $r['recorded_at'], $utcTz))->setTimezone($localTz);
            $r['effective_local_date'] = $effLocal->format('d/m/Y');
            $r['effective_local_time'] = $effLocal->format('H\hi');
            $r['recorded_local_time'] = $recLocal->format('H\hi');
        }
        unset($r);

        return $rows;
    }

    /**
     * Construit la grille hebdomadaire de contrôle des pointages (E17 — jours × aides-soignantes).
     * Statuts calculés :
     * - `on_time` : À l'heure (écart arrivée <= 10 min)
     * - `late` : Retard de N min (arrivée > 10 min après début du créneau)
     * - `early_departure` : Départ anticipé (départ > 10 min avant fin du créneau)
     * - `pending` : À valider (au moins un pointage en statut `pending`)
     * - `missing` : Non pointé (créneau passé sans pointage d'arrivée)
     * - `in_progress` : En poste
     * - `planned` : Prévu (créneau futur)
     *
     * @return array<string, mixed>
     */
    public static function getWeeklyControlGrid(string $mondayDate, int $patientId = 1): array
    {
        $localTz = PlanningService::localTz();
        $utcTz = PlanningService::utcTz();

        $weekStartLocal = new DateTimeImmutable($mondayDate . ' 00:00:00', $localTz);
        $isoDow = (int) $weekStartLocal->format('N');
        if ($isoDow !== 1) {
            $weekStartLocal = $weekStartLocal->modify('-' . ($isoDow - 1) . ' days');
        }
        $weekEndLocal = $weekStartLocal->modify('+6 days');

        $days = [];
        $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStartLocal->modify("+{$i} days");
            $ymd = $d->format('Y-m-d');
            $days[$ymd] = [
                'date' => $ymd,
                'short_day' => $dayNames[(int) $d->format('N')],
                'day_num' => $d->format('d/m'),
                'is_today' => ($ymd === (new DateTimeImmutable('now', $localTz))->format('Y-m-d')),
            ];
        }

        $team = PlanningService::getTeamMembers($patientId);
        $shifts = PlanningService::getShiftsForLocalRange(
            $weekStartLocal->format('Y-m-d'),
            $weekEndLocal->format('Y-m-d'),
            null,
            $patientId
        );

        // Fetch all attendances in a window covering the week + 1 day buffer for night shifts
        $pdo = Database::connection();
        $qStartUtc = $weekStartLocal->modify('-12 hours')->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $qEndUtc = $weekEndLocal->modify('+36 hours')->setTimezone($utcTz)->format('Y-m-d H:i:s');

        $attStmt = $pdo->prepare(
            "SELECT * FROM attendances
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status != 'rejected'
               AND COALESCE(declared_at, recorded_at) >= :qstart
               AND COALESCE(declared_at, recorded_at) <= :qend
             ORDER BY COALESCE(declared_at, recorded_at) ASC, id ASC"
        );
        $attStmt->execute([
            'pid' => $patientId,
            'qstart' => $qStartUtc,
            'qend' => $qEndUtc,
        ]);
        $allAttendances = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        $attByShift = [];
        foreach ($allAttendances as $att) {
            if ($att['shift_id'] !== null) {
                $attByShift[(int) $att['shift_id']][] = $att;
            }
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $grid = [];

        foreach ($team as $member) {
            $uid = (int) $member['id'];
            $grid[$uid] = [
                'user' => $member,
                'cells' => [],
            ];
            foreach ($days as $ymd => $_) {
                $grid[$uid]['cells'][$ymd] = [];
            }
        }

        foreach ($shifts as $sh) {
            if ($sh['user_id'] === null) {
                continue;
            }
            $uid = (int) $sh['user_id'];
            $ymd = (string) $sh['local_date'];
            if (!isset($grid[$uid]['cells'][$ymd])) {
                continue;
            }

            $sid = (int) $sh['id'];
            $shiftAtts = $attByShift[$sid] ?? [];
            $inAtt = null;
            $outAtt = null;
            $hasPending = false;

            foreach ($shiftAtts as $a) {
                if ((string) $a['status'] === 'pending') {
                    $hasPending = true;
                }
                if ((string) $a['type'] === 'in' && $inAtt === null) {
                    $inAtt = $a;
                } elseif ((string) $a['type'] === 'out') {
                    $outAtt = $a;
                }
            }

            $shiftStartTs = (new DateTimeImmutable((string) $sh['start_at'], $utcTz))->getTimestamp();
            $shiftEndTs = (new DateTimeImmutable((string) $sh['end_at'], $utcTz))->getTimestamp();
            $nowTs = (new DateTimeImmutable($nowUtc, $utcTz))->getTimestamp();

            $statusCode = 'planned';
            $statusLabel = 'Prévu';
            $badgeTone = 'neutral';
            $inLocalStr = null;
            $outLocalStr = null;

            if ($inAtt !== null) {
                $inEffUtc = (string) ($inAtt['declared_at'] ?? $inAtt['recorded_at']);
                $inDt = new DateTimeImmutable($inEffUtc, $utcTz);
                $inLocalStr = $inDt->setTimezone($localTz)->format('H\hi');
                $delayMin = (int) round(($inDt->getTimestamp() - $shiftStartTs) / 60);

                if ($outAtt !== null) {
                    $outEffUtc = (string) ($outAtt['declared_at'] ?? $outAtt['recorded_at']);
                    $outDt = new DateTimeImmutable($outEffUtc, $utcTz);
                    $outLocalStr = $outDt->setTimezone($localTz)->format('H\hi');
                    $earlyMin = (int) round(($shiftEndTs - $outDt->getTimestamp()) / 60);
                } else {
                    $earlyMin = 0;
                }

                if ($hasPending) {
                    $statusCode = 'pending';
                    $statusLabel = 'À valider';
                    $badgeTone = 'warn';
                } elseif ($delayMin > 10) {
                    $statusCode = 'late';
                    $statusLabel = "Retard +{$delayMin} min";
                    $badgeTone = 'warn';
                } elseif ($outAtt !== null && $earlyMin > 10) {
                    $statusCode = 'early_departure';
                    $statusLabel = "Départ -{$earlyMin} min";
                    $badgeTone = 'warn';
                } elseif ($outAtt === null && $nowTs < $shiftEndTs) {
                    $statusCode = 'in_progress';
                    $statusLabel = 'En poste';
                    $badgeTone = 'brand';
                } else {
                    $statusCode = 'on_time';
                    $statusLabel = 'À l’heure';
                    $badgeTone = 'ok';
                }
            } else {
                if ($nowTs > $shiftStartTs + 15 * 60) {
                    $statusCode = 'missing';
                    $statusLabel = 'Non pointé';
                    $badgeTone = 'danger';
                } else {
                    $statusCode = 'planned';
                    $statusLabel = 'Prévu';
                    $badgeTone = 'neutral';
                }
            }

            $grid[$uid]['cells'][$ymd][] = [
                'shift_id' => $sid,
                'shift_start_local' => $sh['local_start_time'],
                'shift_end_local' => $sh['local_end_time'],
                'in_local' => $inLocalStr,
                'out_local' => $outLocalStr,
                'status_code' => $statusCode,
                'status_label' => $statusLabel,
                'badge_tone' => $badgeTone,
            ];
        }

        // Also calculate current month hours summary for the header
        $monthYear = (int) $weekStartLocal->format('Y');
        $monthNum = (int) $weekStartLocal->format('n');
        $monthSummary = HoursCalculatorService::calculateMonthlySummary($monthYear, $monthNum, null, $patientId);

        return [
            'week_start' => $weekStartLocal->format('Y-m-d'),
            'week_end' => $weekEndLocal->format('Y-m-d'),
            'prev_week' => $weekStartLocal->modify('-7 days')->format('Y-m-d'),
            'next_week' => $weekStartLocal->modify('+7 days')->format('Y-m-d'),
            'days' => $days,
            'grid' => $grid,
            'month_summary' => $monthSummary,
        ];
    }

    /**
     * Retourne un pointage par son ID.
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT a.*, u.first_name, u.last_name
             FROM attendances a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = :id AND a.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Recherche le créneau planifié correspondant le mieux au pointage.
     */
    private static function findMatchingShiftId(int $userId, string $type, string $refUtc, int $patientId = 1): ?int
    {
        $pdo = Database::connection();
        $refTs = (new DateTimeImmutable($refUtc, new DateTimeZone('UTC')))->getTimestamp();
        $minStart = gmdate('Y-m-d H:i:s', $refTs - 16 * 3600);
        $maxStart = gmdate('Y-m-d H:i:s', $refTs + 6 * 3600);

        $stmt = $pdo->prepare(
            "SELECT id, start_at, end_at
             FROM shifts
             WHERE patient_id = :pid
               AND user_id = :uid
               AND deleted_at IS NULL
               AND status != 'cancelled'
               AND start_at >= :min_st
               AND start_at <= :max_st
             ORDER BY start_at ASC"
        );
        $stmt->execute([
            'pid' => $patientId,
            'uid' => $userId,
            'min_st' => $minStart,
            'max_st' => $maxStart,
        ]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($candidates === []) {
            return null;
        }

        $bestId = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($candidates as $c) {
            $targetUtc = ($type === 'out') ? (string) $c['end_at'] : (string) $c['start_at'];
            $targetTs = (new DateTimeImmutable($targetUtc, new DateTimeZone('UTC')))->getTimestamp();
            $diff = abs($refTs - $targetTs);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestId = (int) $c['id'];
            }
        }

        return $bestId;
    }

    private static function hmacKey(): string
    {
        return hash('sha256', 'PrimeCare-HomeQR-HMAC-2026:' . (string) Config::get('db.name', 'prime_care'));
    }
}
